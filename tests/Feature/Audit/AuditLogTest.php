<?php

use App\Actions\Invoices\RecordInvoicePayment;
use App\Actions\Invoices\StoreInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\StoreTransaction;
use App\Actions\Transactions\UpdateTransaction;
use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->owner = User::factory()->create(['name' => 'Admin']);
    $this->actingAs($this->owner);

    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->client = Client::factory()->create();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** @return array<int, AuditLog> */
function auditFor(string $type): array
{
    return AuditLog::query()->where('auditable_type', $type)->ordered()->get()->all();
}

it('records a transaction being created, and who did it', function () {
    app(StoreTransaction::class)->handle([
        'type' => TransactionType::Income->value,
        'account_id' => $this->account->id,
        'amount' => 3_000_00,
        'date' => '2026-10-10',
        'description' => 'Projek web',
    ]);

    $log = auditFor(Transaction::class)[0];

    expect($log->event)->toBe('created')
        ->and($log->subject)->toBe('Projek web')
        ->and($log->user_id)->toBe($this->owner->id)
        // The name is copied, so the line still reads if the account goes.
        ->and($log->user_name)->toBe('Admin');
});

it('records only the fields that moved, with their before and after', function () {
    $transaction = app(StoreTransaction::class)->handle([
        'type' => TransactionType::Expense->value,
        'account_id' => $this->account->id,
        'amount' => 120_00,
        'date' => '2026-10-10',
        'description' => 'Hosting',
    ]);

    app(UpdateTransaction::class)->handle($transaction, [
        'type' => TransactionType::Expense->value,
        'account_id' => $this->account->id,
        'amount' => 180_00,
        'date' => '2026-10-10',
        'description' => 'Hosting setahun',
    ]);

    $log = AuditLog::query()->where('event', 'updated')->firstOrFail();

    expect($log->changes)->toHaveKeys(['amount', 'description'])
        ->and($log->changes['amount'])->toBe(['from' => 12_000, 'to' => 18_000])
        ->and($log->changes['description'])->toBe([
            'from' => 'Hosting', 'to' => 'Hosting setahun',
        ])
        // The date did not move, so it is not in the line.
        ->and($log->changes)->not->toHaveKey('date');
});

it('says nothing when a save moved nothing worth recording', function () {
    $transaction = app(StoreTransaction::class)->handle([
        'type' => TransactionType::Income->value,
        'account_id' => $this->account->id,
        'amount' => 500_00,
        'date' => '2026-10-10',
        'description' => 'Projek',
    ]);

    $transaction->touch();

    expect(AuditLog::query()->where('event', 'updated')->count())->toBe(0);
});

it('records a transaction being deleted', function () {
    $transaction = app(StoreTransaction::class)->handle([
        'type' => TransactionType::Expense->value,
        'account_id' => $this->account->id,
        'amount' => 50_00,
        'date' => '2026-10-10',
        'description' => 'Kopi',
    ]);

    app(DeleteTransaction::class)->handle($transaction);

    $events = array_map(
        fn (AuditLog $log) => $log->event,
        auditFor(Transaction::class),
    );

    expect($events)->toContain('deleted')
        // The line survives the row it describes.
        ->and(AuditLog::query()->where('event', 'deleted')->value('subject'))
        ->toBe('Kopi');
});

it('records a change made outside the actions', function () {
    // The trail hangs off Eloquent's events, so there is no path around it.
    $transaction = Transaction::factory()->create([
        'account_id' => $this->account->id,
        'amount' => 100_00,
        'description' => 'Langsung',
    ]);

    $transaction->update(['amount' => 250_00]);

    $log = AuditLog::query()->where('event', 'updated')->firstOrFail();

    expect($log->changes['amount'])->toBe(['from' => 10_000, 'to' => 25_000]);
});

it('records an invoice through its whole life', function () {
    $invoice = app(StoreInvoice::class)->handle([
        'client_id' => $this->client->id,
        'issue_date' => '2026-10-01',
        'locale' => 'ms',
        'items' => [['description' => 'Kerja', 'quantity' => 1, 'unit_price' => 1_000_00]],
    ]);

    $invoice->update(['status' => InvoiceStatus::Sent, 'sent_at' => now()]);

    app(RecordInvoicePayment::class)->handle($invoice->refresh(), [
        'account_id' => $this->account->id,
        'amount' => 400_00,
        'date' => '2026-10-10',
    ]);

    app(VoidInvoice::class)->handle($invoice->refresh());

    $events = array_map(fn (AuditLog $log) => $log->event, auditFor(Invoice::class));

    // Created, sent, part paid, voided — each one a line.
    expect($events)->toContain('created')
        ->and(array_count_values($events)['updated'] ?? 0)->toBeGreaterThanOrEqual(3);
});

it('records the status moving to void', function () {
    $invoice = Invoice::factory()->withStatus(InvoiceStatus::Sent)
        ->totalling(500_00)
        ->create(['client_id' => $this->client->id]);

    app(VoidInvoice::class)->handle($invoice);

    $log = AuditLog::query()
        ->where('auditable_type', Invoice::class)
        ->where('event', 'updated')
        ->ordered()
        ->firstOrFail();

    expect($log->changes['status'])->toBe(['from' => 'sent', 'to' => 'void'])
        ->and($log->subject)->toBe($invoice->number);
});

it('turns enums and dates into something a JSON column can hold', function () {
    $transaction = Transaction::factory()->create([
        'account_id' => $this->account->id,
        'type' => TransactionType::Income,
        'date' => '2026-10-01',
    ]);

    $transaction->update([
        'type' => TransactionType::Expense,
        'date' => '2026-10-05',
    ]);

    $log = AuditLog::query()->where('event', 'updated')->firstOrFail();

    expect($log->changes['type'])->toBe(['from' => 'income', 'to' => 'expense'])
        ->and($log->changes['date']['to'])->toContain('2026-10-05');
});

it('records a change with no signed in user', function () {
    // A scheduled command has no user, and the line still has to be written.
    auth()->logout();

    Transaction::factory()->create([
        'account_id' => $this->account->id,
        'description' => 'Oleh command',
    ]);

    $log = auditFor(Transaction::class)[0];

    expect($log->user_id)->toBeNull()
        ->and($log->user_name)->toBeNull()
        ->and($log->subject)->toBe('Oleh command');
});

it('leaves other models out of the trail', function () {
    Client::factory()->create();
    Account::factory()->create();

    expect(AuditLog::query()->count())->toBe(0);
});
