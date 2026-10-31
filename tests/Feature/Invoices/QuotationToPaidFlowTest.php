<?php

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\QuotationStatus;
use App\Models\Account;
use App\Models\Client;
use App\Models\Fund;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\FundBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;

/**
 * The acceptance test for phase 3, walked end to end through the HTTP layer:
 *
 *   quotation -> invoice -> 50% deposit (partial, funds filled)
 *             -> the balance (paid)
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->actingAs(User::factory()->create(['locale' => Locale::Malay]));
    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->client = Client::factory()->speaking(Locale::English)->create([
        'email' => 'client@example.test',
    ]);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** @return array<string, int> Fund slug => balance in sen. */
function flowFundBalances(): array
{
    $byId = app(FundBalanceService::class)->all();

    return Fund::query()->ordered()->get()
        ->mapWithKeys(fn (Fund $fund) => [$fund->slug => $byId[$fund->id] ?? 0])
        ->all();
}

it('goes from quotation to a fully paid invoice', function () {
    // 1. Write a quotation: RM4,000 of work, RM500 off.
    $this->post(route('quotations.store'), [
        'client_id' => $this->client->id,
        'project_id' => null,
        'issue_date' => '2026-10-01',
        'valid_until' => '2026-10-31',
        'status' => 'sent',
        'locale' => 'en',
        'discount' => '500',
        'notes' => 'Thank you',
        'terms' => '50% deposit',
        'items' => [
            ['description' => 'Design', 'quantity' => 1, 'unit_price' => '1500'],
            ['description' => 'Build', 'quantity' => '2.5', 'unit_price' => '1000'],
        ],
    ])->assertRedirect();

    $quotation = Quotation::query()->firstOrFail();

    expect($quotation->number)->toBe('QUO-2026-0001')
        ->and($quotation->subtotal)->toBe(4_000_00)
        ->and($quotation->discount)->toBe(500_00)
        ->and($quotation->total)->toBe(3_500_00)
        ->and($quotation->status)->toBe(QuotationStatus::Sent);

    // 2. The client agrees, so it becomes an invoice.
    $this->post(route('quotations.convert', $quotation))->assertRedirect();

    $invoice = Invoice::query()->firstOrFail();

    expect($invoice->number)->toBe('INV-2026-0001')
        ->and($invoice->total)->toBe(3_500_00)
        // Written in the client's language, not the owner's.
        ->and($invoice->locale)->toBe(Locale::English)
        ->and($invoice->items)->toHaveCount(2)
        ->and($quotation->refresh()->status)->toBe(QuotationStatus::Accepted);

    // 3. A 50% deposit arrives.
    $this->post(route('invoices.pay', $invoice), [
        'account_id' => $this->account->id,
        'amount' => '1750',
        'date' => '2026-10-16',
    ])->assertRedirect();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Partial)
        ->and($invoice->paid_amount)->toBe(1_750_00)
        ->and($invoice->outstanding())->toBe(1_750_00)
        // The deposit is income, so the funds took their share of it.
        ->and(flowFundBalances())->toBe([
            'operating' => 525_00,
            'owner_pay' => 700_00,
            'tax' => 175_00,
            'emergency' => 175_00,
            'capital' => 175_00,
        ]);

    // 4. The balance arrives.
    $this->post(route('invoices.pay', $invoice), [
        'account_id' => $this->account->id,
        'amount' => '1750',
        'date' => '2026-10-30',
    ])->assertRedirect();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_amount)->toBe(3_500_00)
        ->and($invoice->outstanding())->toBe(0)
        ->and($invoice->paid_at?->toDateString())->toBe('2026-10-30')
        // 30 / 40 / 10 / 10 / 10 of the whole RM3,500.
        ->and(flowFundBalances())->toBe([
            'operating' => 1_050_00,
            'owner_pay' => 1_400_00,
            'tax' => 350_00,
            'emergency' => 350_00,
            'capital' => 350_00,
        ])
        // And the money is in the bank.
        ->and(app(AccountBalanceService::class)->for($this->account))
        ->toBe(3_500_00);
});

it('refuses a payment larger than what is still owed', function () {
    $invoice = Invoice::factory()
        ->withStatus(InvoiceStatus::Sent)
        ->totalling(1_000_00)
        ->create(['client_id' => $this->client->id]);

    $this->from(route('invoices.show', $invoice))
        ->post(route('invoices.pay', $invoice), [
            'account_id' => $this->account->id,
            'amount' => '1500',
            'date' => '2026-10-16',
        ])
        ->assertSessionHasErrors('amount');

    expect($invoice->refresh()->paid_amount)->toBe(0);
});

it('will not take a payment on a voided invoice', function () {
    $invoice = Invoice::factory()
        ->withStatus(InvoiceStatus::Void)
        ->totalling(1_000_00)
        ->create(['client_id' => $this->client->id]);

    $this->post(route('invoices.pay', $invoice), [
        'account_id' => $this->account->id,
        'amount' => '100',
        'date' => '2026-10-16',
    ])->assertForbidden();
});

it('will not let a part paid invoice be edited', function () {
    $invoice = Invoice::factory()
        ->withStatus(InvoiceStatus::Partial)
        ->totalling(1_000_00)
        ->create(['client_id' => $this->client->id, 'paid_amount' => 200_00]);

    $this->get(route('invoices.edit', $invoice))->assertForbidden();
});

it('only deletes a draft invoice', function () {
    $draft = Invoice::factory()->withStatus(InvoiceStatus::Draft)
        ->create(['client_id' => $this->client->id]);
    $sent = Invoice::factory()->withStatus(InvoiceStatus::Sent)
        ->create(['client_id' => $this->client->id]);

    $this->delete(route('invoices.destroy', $draft))
        ->assertRedirect(route('invoices.index'));
    $this->delete(route('invoices.destroy', $sent))->assertForbidden();

    expect(Invoice::query()->count())->toBe(1);
});

it('removes a payment and puts the invoice back', function () {
    // Not yet due, so taking the payment away leaves it merely sent.
    $invoice = Invoice::factory()->withStatus(InvoiceStatus::Sent)
        ->totalling(1_000_00)
        ->dueOn('2026-11-30')
        ->create(['client_id' => $this->client->id]);

    $this->post(route('invoices.pay', $invoice), [
        'account_id' => $this->account->id,
        'amount' => '1000',
        'date' => '2026-10-16',
    ])->assertRedirect();

    $payment = $invoice->refresh()->payments()->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and(flowFundBalances()['operating'])->toBe(300_00);

    $this->from(route('invoices.show', $invoice))
        ->delete(route('invoices.payments.destroy', [$invoice, $payment]))
        ->assertInertiaFlash('toast.type', 'success');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->paid_amount)->toBe(0)
        ->and(flowFundBalances()['operating'])->toBe(0);
});

it('will not remove a payment that belongs to another invoice', function () {
    $first = Invoice::factory()->withStatus(InvoiceStatus::Sent)
        ->totalling(500_00)->create(['client_id' => $this->client->id]);
    $second = Invoice::factory()->withStatus(InvoiceStatus::Sent)
        ->totalling(500_00)->create(['client_id' => $this->client->id]);

    $this->post(route('invoices.pay', $first), [
        'account_id' => $this->account->id,
        'amount' => '500',
        'date' => '2026-10-16',
    ])->assertRedirect();

    $payment = $first->refresh()->payments()->firstOrFail();

    $this->delete(route('invoices.payments.destroy', [$second, $payment]))
        ->assertNotFound();
});

it('numbers documents in sequence across the whole flow', function () {
    foreach (range(1, 3) as $ignored) {
        $this->post(route('quotations.store'), [
            'client_id' => $this->client->id,
            'issue_date' => '2026-10-01',
            'valid_until' => '2026-10-31',
            'status' => 'draft',
            'locale' => 'ms',
            'discount' => '0',
            'items' => [['description' => 'Kerja', 'quantity' => 1, 'unit_price' => '100']],
        ])->assertRedirect();
    }

    expect(Quotation::query()->orderBy('id')->pluck('number')->all())
        ->toBe(['QUO-2026-0001', 'QUO-2026-0002', 'QUO-2026-0003']);
});
