<?php

use App\Actions\Recurring\PayRecurringCost;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\RecurringInterval;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringItem;
use App\Models\User;
use App\Notifications\RecurringCostDue;
use App\Notifications\RecurringInvoiceDrafted;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 01:15:00');

    $this->seed(SettingSeeder::class);
    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->owner = User::factory()->create(['locale' => Locale::Malay]);
    $this->client = Client::factory()->speaking(Locale::English)->create();

    Notification::fake();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('drafts an invoice once the warning window opens', function () {
    // The acceptance case: a yearly hosting charge due in 10 days.
    $item = RecurringItem::factory()
        ->clientBilling($this->client)
        ->autoGenerating()
        ->every(RecurringInterval::Yearly)
        ->dueOn('2026-10-25')
        ->remindingDaysBefore(14)
        ->amount(1_200_00)
        ->create(['name' => 'Hosting tahunan']);

    $this->artisan('recurring:process')->assertSuccessful();

    $invoice = Invoice::query()->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->recurring_item_id)->toBe($item->id)
        ->and($invoice->client_id)->toBe($this->client->id)
        ->and($invoice->total)->toBe(1_200_00)
        // The invoice is dated on the charge, not on the day it was drafted.
        ->and($invoice->issue_date->toDateString())->toBe('2026-10-25')
        // And written in the client's language, not the owner's.
        ->and($invoice->locale)->toBe(Locale::English);

    // The item has moved on to next year.
    expect($item->refresh()->next_date->toDateString())->toBe('2027-10-25');

    Notification::assertSentTo($this->owner, RecurringInvoiceDrafted::class);
});

it('does not draft a second invoice when it runs again', function () {
    // The other half of the acceptance case.
    RecurringItem::factory()
        ->clientBilling($this->client)
        ->autoGenerating()
        ->every(RecurringInterval::Yearly)
        ->dueOn('2026-10-25')
        ->create();

    $this->artisan('recurring:process');
    $this->artisan('recurring:process');
    $this->artisan('recurring:process');

    expect(Invoice::query()->count())->toBe(1);
});

it('leaves an item alone before its window opens', function () {
    RecurringItem::factory()
        ->clientBilling($this->client)
        ->autoGenerating()
        ->dueOn('2026-11-30')
        ->remindingDaysBefore(14)
        ->create();

    $this->artisan('recurring:process');

    expect(Invoice::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('drafts on the first day of the window and not a day earlier', function () {
    $item = RecurringItem::factory()
        ->clientBilling($this->client)
        ->autoGenerating()
        ->dueOn('2026-10-29')
        ->remindingDaysBefore(14)
        ->create();

    $this->artisan('recurring:process', ['--date' => '2026-10-14']);
    expect(Invoice::query()->count())->toBe(0);

    $this->artisan('recurring:process', ['--date' => '2026-10-15']);
    expect(Invoice::query()->count())->toBe(1)
        ->and($item->refresh()->next_date->toDateString())->toBe('2026-11-29');
});

it('catches up on every charge that went by while the app was closed', function () {
    // Monthly, three months behind: three invoices are genuinely owed.
    RecurringItem::factory()
        ->clientBilling($this->client)
        ->autoGenerating()
        ->every(RecurringInterval::Monthly)
        ->dueOn('2026-08-05')
        ->remindingDaysBefore(0)
        ->amount(500_00)
        ->create();

    $this->artisan('recurring:process');

    $dates = Invoice::query()->orderBy('issue_date')->pluck('issue_date')
        ->map(fn ($date) => $date->toDateString())->all();

    expect($dates)->toBe(['2026-08-05', '2026-09-05', '2026-10-05'])
        ->and(Invoice::query()->sum('total'))->toBe(1_500_00);
});

it('skips a charge whose invoice was deleted rather than making it again', function () {
    $item = RecurringItem::factory()
        ->clientBilling($this->client)
        ->autoGenerating()
        ->every(RecurringInterval::Monthly)
        ->dueOn('2026-10-05')
        ->remindingDaysBefore(0)
        ->create();

    $this->artisan('recurring:process');
    $invoice = Invoice::query()->firstOrFail();

    // Throwing the draft away was a decision; it must not come back.
    $invoice->delete();
    $item->update(['next_date' => '2026-10-05']);
    $this->artisan('recurring:process');

    expect(Invoice::query()->count())->toBe(0)
        ->and(Invoice::withTrashed()->count())->toBe(1);
});

it('reminds about a cost of your own without touching its date', function () {
    $item = RecurringItem::factory()
        ->ownCost()
        ->dueOn('2026-10-20')
        ->remindingDaysBefore(14)
        ->amount(49_90)
        ->create(['name' => 'Langganan Figma']);

    $this->artisan('recurring:process')->assertSuccessful();

    Notification::assertSentTo($this->owner, RecurringCostDue::class);

    // Only marking it paid moves an own cost along.
    expect($item->refresh()->next_date->toDateString())->toBe('2026-10-20')
        ->and($item->last_reminded_at)->not->toBeNull();
});

it('reminds about a cost only once per charge', function () {
    RecurringItem::factory()
        ->ownCost()
        ->dueOn('2026-10-20')
        ->remindingDaysBefore(14)
        ->create();

    $this->artisan('recurring:process', ['--date' => '2026-10-15']);
    $this->artisan('recurring:process', ['--date' => '2026-10-16']);
    $this->artisan('recurring:process', ['--date' => '2026-10-17']);

    // Otherwise the owner gets the same reminder every day for a fortnight.
    Notification::assertSentToTimes($this->owner, RecurringCostDue::class, 1);
});

it('reminds again once the charge has been paid and the next one comes round', function () {
    $item = RecurringItem::factory()
        ->ownCost()
        ->every(RecurringInterval::Monthly)
        ->dueOn('2026-10-20')
        ->remindingDaysBefore(14)
        ->create();

    $this->artisan('recurring:process', ['--date' => '2026-10-15']);

    // Paid, so the item moves on and the slate is clean.
    app(PayRecurringCost::class)->handle($item, [
        'account_id' => Account::factory()->create()->id,
        'date' => '2026-10-20',
    ]);

    expect($item->refresh()->next_date->toDateString())->toBe('2026-11-20');

    $this->artisan('recurring:process', ['--date' => '2026-11-10']);

    Notification::assertSentToTimes($this->owner, RecurringCostDue::class, 2);
});

it('reminds about a client charge that is not drafted automatically', function () {
    RecurringItem::factory()
        ->clientBilling($this->client)
        ->dueOn('2026-10-20')
        ->remindingDaysBefore(14)
        ->create(['auto_generate' => false]);

    $this->artisan('recurring:process');

    // No invoice is written; the owner raises it themselves.
    expect(Invoice::query()->count())->toBe(0);
    Notification::assertSentTo($this->owner, RecurringCostDue::class);
});

it('ignores items that have been switched off', function () {
    RecurringItem::factory()
        ->clientBilling($this->client)
        ->autoGenerating()
        ->dueOn('2026-10-16')
        ->inactive()
        ->create();
    RecurringItem::factory()->ownCost()->dueOn('2026-10-16')->inactive()->create();

    $this->artisan('recurring:process');

    expect(Invoice::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('does nothing at all when there is nothing to do', function () {
    $this->artisan('recurring:process')
        ->expectsOutputToContain('0 invoice(s) drafted, 0 reminder(s) sent')
        ->assertSuccessful();
});

it('reports what it did', function () {
    RecurringItem::factory()->clientBilling($this->client)->autoGenerating()
        ->dueOn('2026-10-16')->create();
    RecurringItem::factory()->ownCost()->dueOn('2026-10-16')->create();

    $this->artisan('recurring:process')
        ->expectsOutputToContain('1 invoice(s) drafted, 1 reminder(s) sent');
});
