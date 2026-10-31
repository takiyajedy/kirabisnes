<?php

use App\Actions\Invoices\RecordInvoicePayment;
use App\Actions\Invoices\RefreshInvoiceState;
use App\Actions\Invoices\StoreInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Transactions\DeleteTransaction;
use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Fund;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Services\AccountBalanceService;
use App\Services\FundBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);

    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->client = Client::factory()->create();
    $this->pay = app(RecordInvoicePayment::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** An invoice for one line of the given amount, already sent. */
function sentInvoice(int $sen): Invoice
{
    $invoice = app(StoreInvoice::class)->handle([
        'client_id' => test()->client->id,
        'issue_date' => '2026-10-01',
        'locale' => 'ms',
        'items' => [
            ['description' => 'Projek web', 'quantity' => 1, 'unit_price' => $sen],
        ],
    ]);

    $invoice->update(['status' => InvoiceStatus::Sent, 'sent_at' => now()]);

    return $invoice->refresh();
}

/** @return array<string, int> Fund slug => balance in sen. */
function fundBalances(): array
{
    $byId = app(FundBalanceService::class)->all();

    return Fund::query()->ordered()->get()
        ->mapWithKeys(fn (Fund $fund) => [$fund->slug => $byId[$fund->id] ?? 0])
        ->all();
}

it('records a deposit as a partial payment', function () {
    $invoice = sentInvoice(3_000_00);

    $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 1_500_00,
        'date' => '2026-10-10',
    ]);

    $invoice->refresh();

    expect($invoice->paid_amount)->toBe(1_500_00)
        ->and($invoice->status)->toBe(InvoiceStatus::Partial)
        ->and($invoice->outstanding())->toBe(1_500_00)
        ->and($invoice->paid_at)->toBeNull();
});

it('marks the invoice paid once the balance comes in', function () {
    $invoice = sentInvoice(3_000_00);

    $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 1_500_00,
        'date' => '2026-10-10',
    ]);
    $this->pay->handle($invoice->refresh(), [
        'account_id' => $this->account->id,
        'amount' => 1_500_00,
        'date' => '2026-10-20',
    ]);

    $invoice->refresh();

    expect($invoice->paid_amount)->toBe(3_000_00)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->outstanding())->toBe(0)
        ->and($invoice->paid_at?->toDateString())->toBe('2026-10-20');
});

it('splits an invoice payment across the funds like any other income', function () {
    $invoice = sentInvoice(3_000_00);

    $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 3_000_00,
        'date' => '2026-10-10',
    ]);

    // PLAN.md: RM3,000 -> 900 / 1,200 / 300 / 300 / 300
    expect(fundBalances())->toBe([
        'operating' => 900_00,
        'owner_pay' => 1_200_00,
        'tax' => 300_00,
        'emergency' => 300_00,
        'capital' => 300_00,
    ]);
});

it('splits a deposit on the deposit, not on the invoice total', function () {
    $invoice = sentInvoice(3_000_00);

    $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 1_000_00,
        'date' => '2026-10-10',
    ]);

    // 30% of the RM1,000 that actually arrived.
    expect(fundBalances()['operating'])->toBe(300_00);
});

it('tags the payment with the invoice, client and project', function () {
    $project = Project::factory()->create(['client_id' => $this->client->id]);
    $invoice = sentInvoice(500_00);
    $invoice->update(['project_id' => $project->id]);

    $payment = $this->pay->handle($invoice->refresh(), [
        'account_id' => $this->account->id,
        'amount' => 500_00,
        'date' => '2026-10-10',
    ]);

    expect($payment->invoice_id)->toBe($invoice->id)
        ->and($payment->client_id)->toBe($this->client->id)
        ->and($payment->project_id)->toBe($project->id)
        ->and($payment->type->value)->toBe('income')
        ->and($payment->reference)->toBe($invoice->number);
});

it('puts the money into the account the payment names', function () {
    $invoice = sentInvoice(800_00);

    $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 800_00,
        'date' => '2026-10-10',
    ]);

    expect(app(AccountBalanceService::class)->for($this->account))
        ->toBe(800_00);
});

it('takes the invoice back to partial when a payment is deleted', function () {
    $invoice = sentInvoice(1_000_00);

    $first = $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 400_00,
        'date' => '2026-10-10',
    ]);
    $this->pay->handle($invoice->refresh(), [
        'account_id' => $this->account->id,
        'amount' => 600_00,
        'date' => '2026-10-11',
    ]);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);

    app(DeleteTransaction::class)->handle($first);
    app(RefreshInvoiceState::class)->handle($invoice->refresh());

    expect($invoice->refresh()->paid_amount)->toBe(600_00)
        ->and($invoice->status)->toBe(InvoiceStatus::Partial)
        ->and($invoice->paid_at)->toBeNull();
});

it('takes the invoice back to sent when every payment is gone', function () {
    $invoice = sentInvoice(1_000_00);

    $payment = $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 1_000_00,
        'date' => '2026-10-10',
    ]);

    app(DeleteTransaction::class)->handle($payment);
    app(RefreshInvoiceState::class)->handle($invoice->refresh());

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->paid_amount)->toBe(0);
});

it('voids an invoice and takes its money back out of the funds', function () {
    $invoice = sentInvoice(2_000_00);

    $this->pay->handle($invoice, [
        'account_id' => $this->account->id,
        'amount' => 2_000_00,
        'date' => '2026-10-10',
    ]);

    expect(fundBalances()['operating'])->toBe(600_00);

    app(VoidInvoice::class)->handle($invoice->refresh());

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Void)
        ->and($invoice->paid_amount)->toBe(0)
        // The income never belonged to the business, so the funds let it go.
        ->and(fundBalances()['operating'])->toBe(0)
        ->and($invoice->payments()->count())->toBe(0);
});

it('leaves a voided invoice alone when the state is refreshed', function () {
    $invoice = sentInvoice(500_00);
    $invoice->update(['status' => InvoiceStatus::Void]);

    app(RefreshInvoiceState::class)->handle($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Void);
});

it('works out the due date from the payment terms setting', function () {
    Setting::put('default_payment_terms_days', 30);

    $invoice = app(StoreInvoice::class)->handle([
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-01',
        'locale' => 'ms',
        'items' => [['description' => 'Kerja', 'quantity' => 1, 'unit_price' => 100_00]],
    ]);

    expect($invoice->due_date->toDateString())->toBe('2026-10-31');
});
