<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Fund;
use App\Models\Transaction;
use App\Services\DashboardService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->dashboard = app(DashboardService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function record(TransactionType $type, int $sen, string $date, array $attributes = []): Transaction
{
    return app(StoreTransaction::class)->handle([
        'type' => $type->value,
        'account_id' => test()->account->id,
        'amount' => $sen,
        'date' => $date,
        'description' => 'Test',
        ...$attributes,
    ]);
}

it('adds up money in and money out for the window', function () {
    record(TransactionType::Income, 3_000_00, '2026-10-02');
    record(TransactionType::Income, 1_500_00, '2026-10-20');
    record(TransactionType::Expense, 250_00, '2026-10-05');
    record(TransactionType::Expense, 100_00, '2026-10-06');

    // Outside the window, so none of it counts.
    record(TransactionType::Income, 9_999_00, '2026-09-30');

    $summary = $this->dashboard->summary(Period::resolve('this_month'));

    expect($summary['income'])->toBe(4_500_00)
        ->and($summary['expense'])->toBe(350_00)
        ->and($summary['net'])->toBe(4_150_00);
});

it('leaves owner draws and capital out of the profit', function () {
    record(TransactionType::Income, 1_000_00, '2026-10-02');
    record(TransactionType::CapitalInjection, 5_000_00, '2026-10-03');
    record(TransactionType::OwnerDraw, 400_00, '2026-10-04');
    record(TransactionType::Expense, 100_00, '2026-10-05');

    $summary = $this->dashboard->summary(Period::resolve('this_month'));

    // The owner's own money moving in and out is not trading.
    expect($summary['income'])->toBe(1_000_00)
        ->and($summary['expense'])->toBe(100_00)
        ->and($summary['net'])->toBe(900_00);
});

it('works out the change against the previous window', function () {
    record(TransactionType::Income, 200_00, '2026-09-10');
    record(TransactionType::Income, 300_00, '2026-10-10');

    $summary = $this->dashboard->summary(Period::resolve('this_month'));

    // 200 -> 300 is a half as much again.
    expect($summary['income_change'])->toBe(50.0);
});

it('reports a fall as a negative change', function () {
    record(TransactionType::Expense, 400_00, '2026-09-10');
    record(TransactionType::Expense, 100_00, '2026-10-10');

    expect($this->dashboard->summary(Period::resolve('this_month'))['expense_change'])
        ->toBe(-75.0);
});

it('has no percentage to report when the previous window was empty', function () {
    record(TransactionType::Income, 500_00, '2026-10-10');

    $summary = $this->dashboard->summary(Period::resolve('this_month'));

    expect($summary['income_change'])->toBeNull()
        ->and($summary['expense_change'])->toBeNull();
});

it('measures a change from a loss to a profit against the size of the loss', function () {
    // Last month: 100 in, 300 out, so a 200 loss.
    record(TransactionType::Income, 100_00, '2026-09-10');
    record(TransactionType::Expense, 300_00, '2026-09-11');
    // This month: 400 in, 200 out, so a 200 profit.
    record(TransactionType::Income, 400_00, '2026-10-10');
    record(TransactionType::Expense, 200_00, '2026-10-11');

    $summary = $this->dashboard->summary(Period::resolve('this_month'));

    expect($summary['net'])->toBe(200_00)
        // From -200 to +200 is a swing of 400 against a 200 base.
        ->and($summary['net_change'])->toBe(200.0);
});

it('attaches a balance to every fund', function () {
    record(TransactionType::Income, 3_000_00, '2026-10-02');

    $balances = $this->dashboard->funds()
        ->mapWithKeys(fn (Fund $fund) => [$fund->slug => $fund->balance])
        ->all();

    // The phase 1 split: 30 / 40 / 10 / 10 / 10 of RM3,000.
    expect($balances)->toBe([
        'operating' => 900_00,
        'owner_pay' => 1_200_00,
        'tax' => 300_00,
        'emergency' => 300_00,
        'capital' => 300_00,
    ]);
});

it('builds twelve months of buckets, including the empty ones', function () {
    record(TransactionType::Income, 100_00, '2026-10-05');
    record(TransactionType::Expense, 40_00, '2026-10-06');
    record(TransactionType::Income, 700_00, '2025-11-20');

    $series = $this->dashboard->monthlySeries(
        Period::resolve('this_month')->chartEndsOn(),
    );

    expect($series)->toHaveCount(12)
        ->and($series[0]['month'])->toBe('2025-11-01')
        ->and($series[0]['income'])->toBe(700_00)
        ->and($series[11]['month'])->toBe('2026-10-01')
        ->and($series[11]['income'])->toBe(100_00)
        ->and($series[11]['expense'])->toBe(40_00)
        // December 2025 had nothing, but it still gets a bar.
        ->and($series[1])->toBe(['month' => '2025-12-01', 'income' => 0, 'expense' => 0]);
});

it('drops anything older than the twelve month window', function () {
    record(TransactionType::Income, 500_00, '2025-10-31');

    $series = $this->dashboard->monthlySeries(
        Period::resolve('this_month')->chartEndsOn(),
    );

    expect(array_sum(array_column($series, 'income')))->toBe(0);
});

it('groups expenses by category, largest first', function () {
    $hosting = Category::query()->whereJsonContains('name->en', 'Hosting & Server')->firstOrFail();
    $fuel = Category::query()->whereJsonContains('name->en', 'Fuel / Toll / Parking')->firstOrFail();

    record(TransactionType::Expense, 50_00, '2026-10-02', ['category_id' => $hosting->id]);
    record(TransactionType::Expense, 30_00, '2026-10-03', ['category_id' => $hosting->id]);
    record(TransactionType::Expense, 200_00, '2026-10-04', ['category_id' => $fuel->id]);

    $slices = $this->dashboard->expensesByCategory(Period::resolve('this_month'));

    expect($slices)->toHaveCount(2)
        ->and($slices[0]['amount'])->toBe(200_00)
        ->and($slices[0]['slot'])->toBe(0)
        ->and($slices[1]['amount'])->toBe(80_00)
        ->and($slices[1]['slot'])->toBe(1);
});

it('gathers everything past the fifth category into one slice', function () {
    $categories = Category::query()->where('type', 'expense')->take(8)->get();

    // Descending amounts, so the order of the slices is predictable.
    foreach ($categories->values() as $index => $category) {
        record(TransactionType::Expense, (8 - $index) * 10_00, '2026-10-02', [
            'category_id' => $category->id,
        ]);
    }

    $slices = $this->dashboard->expensesByCategory(Period::resolve('this_month'));

    // Five named slices plus the rest.
    expect($slices)->toHaveCount(6)
        ->and($slices[5]['slot'])->toBe(-1)
        // The 6th, 7th and 8th: 30 + 20 + 10.
        ->and($slices[5]['amount'])->toBe(60_00)
        ->and($slices[5]['name'])->toBe(__('dashboard.other_categories'));
});

it('labels expenses with no category', function () {
    record(TransactionType::Expense, 25_00, '2026-10-02');

    $slices = $this->dashboard->expensesByCategory(Period::resolve('this_month'));

    expect($slices[0]['name'])->toBe(__('dashboard.uncategorised'));
});

it('lists the five latest transactions in the window, newest first', function () {
    foreach (range(1, 7) as $day) {
        record(TransactionType::Expense, $day * 10_00, sprintf('2026-10-%02d', $day));
    }

    $recent = $this->dashboard->recentTransactions(Period::resolve('this_month'));

    expect($recent)->toHaveCount(5)
        ->and($recent->first()->date->toDateString())->toBe('2026-10-07')
        ->and($recent->last()->date->toDateString())->toBe('2026-10-03');
});

it('counts every active account in the total balance', function () {
    $second = Account::factory()->create(['opening_balance' => 1_000_00]);

    record(TransactionType::Income, 3_000_00, '2026-10-02');
    record(TransactionType::Expense, 50_00, '2026-10-03');

    // Last month's money is still in the bank, so the balance includes it.
    record(TransactionType::Income, 500_00, '2026-09-02');

    expect($this->dashboard->totalBalance())
        ->toBe(1_000_00 + 3_000_00 - 50_00 + 500_00)
        ->and($second->refresh()->opening_balance)->toBe(1_000_00);
});
