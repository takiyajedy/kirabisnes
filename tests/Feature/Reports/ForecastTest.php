<?php

use App\Enums\InvoiceStatus;
use App\Enums\RecurringInterval;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\RecurringItem;
use App\Services\CashFlowForecastService;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);

    $this->account = Account::factory()->create(['opening_balance' => 5_000_00]);
    $this->client = Client::factory()->create();
    $this->forecast = app(CashFlowForecastService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** @return array<int, array<string, int|string>> days => horizon row. */
function horizonsByDay(array $report): array
{
    $rows = [];

    foreach ($report['horizons'] as $row) {
        $rows[$row['days']] = $row;
    }

    return $rows;
}

it('starts from the money actually on hand', function () {
    $report = $this->forecast->report();

    expect($report['opening'])->toBe(5_000_00)
        ->and($report['points'][0]['balance'])->toBe(5_000_00)
        ->and($report['points'][0]['date'])->toBe('2026-10-15');
});

it('reports a point for every day up to the horizon', function () {
    $report = $this->forecast->report(90);

    // Today plus ninety days.
    expect($report['points'])->toHaveCount(91)
        ->and($report['points'][90]['date'])->toBe('2027-01-13')
        ->and($report['horizons'])->toHaveCount(3);
});

it('adds an unpaid invoice on its due date', function () {
    Invoice::factory()->withStatus(InvoiceStatus::Sent)
        ->totalling(3_000_00)->dueOn('2026-11-10')
        ->create(['client_id' => $this->client->id]);

    $horizons = horizonsByDay($this->forecast->report());

    // 26 days away, so inside the first horizon.
    expect($horizons[30]['balance'])->toBe(8_000_00)
        ->and($horizons[30]['in'])->toBe(3_000_00);
});

it('adds only what is still owed on a part paid invoice', function () {
    Invoice::factory()->withStatus(InvoiceStatus::Partial)
        ->totalling(3_000_00)->dueOn('2026-11-10')
        ->create(['client_id' => $this->client->id, 'paid_amount' => 1_200_00]);

    expect(horizonsByDay($this->forecast->report())[30]['in'])->toBe(1_800_00);
});

it('brings an overdue invoice to the start of the window', function () {
    // The money is owed now; leaving it out would read as written off.
    Invoice::factory()->withStatus(InvoiceStatus::Overdue)
        ->totalling(2_000_00)->dueOn('2026-09-01')
        ->create(['client_id' => $this->client->id]);

    $report = $this->forecast->report();

    expect($report['points'][0]['in'])->toBe(2_000_00)
        ->and($report['points'][0]['balance'])->toBe(7_000_00);
});

it('leaves drafts, paid and voided invoices out', function () {
    foreach ([InvoiceStatus::Draft, InvoiceStatus::Paid, InvoiceStatus::Void] as $status) {
        Invoice::factory()->withStatus($status)
            ->totalling(9_000_00)->dueOn('2026-11-01')
            ->create(['client_id' => $this->client->id]);
    }

    expect($this->forecast->report()['expected_in'])->toBe(0);
});

it('adds recurring client charges and takes recurring costs off', function () {
    RecurringItem::factory()->clientBilling($this->client)
        ->every(RecurringInterval::Monthly)->dueOn('2026-11-01')
        ->amount(800_00)->create();
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)->dueOn('2026-11-05')
        ->amount(120_00)->create();

    $horizons = horizonsByDay($this->forecast->report(90));

    // Three monthly turns of each inside ninety days.
    expect($horizons[90]['in'])->toBe(2_400_00)
        ->and($horizons[90]['out'])->toBe(360_00)
        ->and($horizons[90]['balance'])->toBe(5_000_00 + 2_400_00 - 360_00);
});

it('grows the horizons as the window widens', function () {
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)->dueOn('2026-11-01')
        ->amount(100_00)->create();

    $horizons = horizonsByDay($this->forecast->report(90));

    expect($horizons[30]['out'])->toBe(100_00)
        ->and($horizons[60]['out'])->toBe(200_00)
        ->and($horizons[90]['out'])->toBe(300_00);
});

it('leaves switched off recurring items out', function () {
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)->dueOn('2026-11-01')
        ->amount(500_00)->inactive()->create();

    expect($this->forecast->report()['expected_out'])->toBe(0);
});

it('shows the balance going under when it does', function () {
    $broke = Account::factory()->create(['opening_balance' => 0]);
    $this->account->update(['opening_balance' => 100_00]);

    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)->dueOn('2026-11-01')
        ->amount(500_00)->create();

    $horizons = horizonsByDay($this->forecast->report());

    expect($horizons[30]['balance'])->toBe(-400_00)
        ->and($broke->refresh()->opening_balance)->toBe(0);
});

it('runs flat with nothing on the books', function () {
    $report = $this->forecast->report();
    $balances = array_column($report['points'], 'balance');

    expect(array_unique($balances))->toBe([5_000_00])
        ->and($report['expected_in'])->toBe(0)
        ->and($report['expected_out'])->toBe(0);
});
