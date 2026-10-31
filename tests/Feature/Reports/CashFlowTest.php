<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Services\AccountBalanceService;
use App\Services\CashFlowService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);

    $this->bank = Account::factory()->create([
        'name' => 'Bank',
        'opening_balance' => 1_000_00,
        'sort_order' => 0,
    ]);
    $this->cash = Account::factory()->create([
        'name' => 'Tunai',
        'opening_balance' => 0,
        'sort_order' => 1,
    ]);

    $this->report = app(CashFlowService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** @return array<string, array<string, int>> Account name => row. */
function cashFlowRows(array $report): array
{
    $rows = [];

    foreach ($report['rows'] as $row) {
        $rows[$row['name']] = $row;
    }

    return $rows;
}

function flowRecord(TransactionType $type, int $sen, string $date, Account $account, array $extra = []): void
{
    app(StoreTransaction::class)->handle([
        'type' => $type->value,
        'account_id' => $account->id,
        'amount' => $sen,
        'date' => $date,
        'description' => 'Test',
        ...$extra,
    ]);
}

it('shows what went in and out of each account', function () {
    flowRecord(TransactionType::Income, 3_000_00, '2026-10-02', $this->bank);
    flowRecord(TransactionType::Expense, 500_00, '2026-10-05', $this->bank);
    flowRecord(TransactionType::Expense, 80_00, '2026-10-06', $this->cash);

    $rows = cashFlowRows($this->report->report(Period::resolve('this_month')));

    expect($rows['Bank']['opening'])->toBe(1_000_00)
        ->and($rows['Bank']['in'])->toBe(3_000_00)
        ->and($rows['Bank']['out'])->toBe(500_00)
        ->and($rows['Bank']['closing'])->toBe(3_500_00)
        ->and($rows['Tunai']['out'])->toBe(80_00)
        ->and($rows['Tunai']['closing'])->toBe(-80_00);
});

it('counts the owner drawing and putting money in', function () {
    // This is cash flow, not profit: the owner's own money moves the bank
    // balance just like anything else.
    flowRecord(TransactionType::CapitalInjection, 5_000_00, '2026-10-02', $this->bank);
    flowRecord(TransactionType::OwnerDraw, 1_200_00, '2026-10-20', $this->bank);

    $rows = cashFlowRows($this->report->report(Period::resolve('this_month')));

    expect($rows['Bank']['in'])->toBe(5_000_00)
        ->and($rows['Bank']['out'])->toBe(1_200_00)
        ->and($rows['Bank']['closing'])->toBe(4_800_00);
});

it('shows a transfer on both sides and cancels it in the totals', function () {
    flowRecord(TransactionType::Transfer, 300_00, '2026-10-10', $this->bank, [
        'to_account_id' => $this->cash->id,
    ]);

    $report = $this->report->report(Period::resolve('this_month'));
    $rows = cashFlowRows($report);

    expect($rows['Bank']['out'])->toBe(300_00)
        ->and($rows['Tunai']['in'])->toBe(300_00)
        // Nothing left the business, so the net is nil.
        ->and($report['totals']['net'])->toBe(0)
        ->and($report['totals']['closing'])->toBe(1_000_00);
});

it('carries last month into the opening balance', function () {
    flowRecord(TransactionType::Income, 2_000_00, '2026-09-10', $this->bank);
    flowRecord(TransactionType::Income, 400_00, '2026-10-10', $this->bank);

    $rows = cashFlowRows($this->report->report(Period::resolve('this_month')));

    expect($rows['Bank']['opening'])->toBe(3_000_00)
        ->and($rows['Bank']['in'])->toBe(400_00)
        ->and($rows['Bank']['closing'])->toBe(3_400_00);
});

it('carries a transfer received last month into the opening balance', function () {
    flowRecord(TransactionType::Transfer, 250_00, '2026-09-20', $this->bank, [
        'to_account_id' => $this->cash->id,
    ]);

    $rows = cashFlowRows($this->report->report(Period::resolve('this_month')));

    expect($rows['Tunai']['opening'])->toBe(250_00)
        ->and($rows['Bank']['opening'])->toBe(750_00);
});

it('closes on the same total the account balances show', function () {
    flowRecord(TransactionType::Income, 3_000_00, '2026-09-02', $this->bank);
    flowRecord(TransactionType::Expense, 120_00, '2026-10-03', $this->cash);
    flowRecord(TransactionType::Transfer, 500_00, '2026-10-04', $this->bank, [
        'to_account_id' => $this->cash->id,
    ]);

    $report = $this->report->report(Period::resolve('this_month'));

    // The report and the ledger must not be able to disagree.
    expect($report['totals']['closing'])
        ->toBe(app(AccountBalanceService::class)->total());
});

it('lists every account, even the quiet ones', function () {
    $report = $this->report->report(Period::resolve('this_month'));

    expect($report['rows'])->toHaveCount(2)
        ->and($report['totals']['in'])->toBe(0)
        ->and($report['totals']['opening'])->toBe(1_000_00);
});
