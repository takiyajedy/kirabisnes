<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Services\DashboardService;
use App\Services\ProfitLossService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->report = app(ProfitLossService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function pnlRecord(TransactionType $type, int $sen, string $date, array $attributes = []): void
{
    app(StoreTransaction::class)->handle([
        'type' => $type->value,
        'account_id' => test()->account->id,
        'amount' => $sen,
        'date' => $date,
        'description' => 'Test',
        ...$attributes,
    ]);
}

function categoryNamed(string $english): Category
{
    return Category::query()->whereJsonContains('name->en', $english)->firstOrFail();
}

it('adds income and expenses up into a profit', function () {
    pnlRecord(TransactionType::Income, 5_000_00, '2026-10-02');
    pnlRecord(TransactionType::Income, 2_000_00, '2026-10-20');
    pnlRecord(TransactionType::Expense, 1_200_00, '2026-10-05');

    $report = $this->report->report(Period::resolve('this_month'));

    expect($report['income'])->toBe(7_000_00)
        ->and($report['expense'])->toBe(1_200_00)
        ->and($report['net'])->toBe(5_800_00);
});

it('matches the figures the dashboard shows', function () {
    // The acceptance criterion: the report and the dashboard agree.
    pnlRecord(TransactionType::Income, 3_300_00, '2026-10-03');
    pnlRecord(TransactionType::Expense, 870_00, '2026-10-04');
    pnlRecord(TransactionType::OwnerDraw, 1_000_00, '2026-10-05');
    pnlRecord(TransactionType::CapitalInjection, 5_000_00, '2026-10-06');

    $period = Period::resolve('this_month');
    $report = $this->report->report($period);
    $dashboard = app(DashboardService::class)->summary($period);

    expect($report['income'])->toBe($dashboard['income'])
        ->and($report['expense'])->toBe($dashboard['expense'])
        ->and($report['net'])->toBe($dashboard['net'])
        // And neither counts the owner's own money moving.
        ->and($report['income'])->toBe(3_300_00)
        ->and($report['net'])->toBe(2_430_00);
});

it('breaks the sides down by category, largest first', function () {
    $web = categoryNamed('Web Project');
    $maintenance = categoryNamed('Maintenance');
    $hosting = categoryNamed('Hosting & Server');

    pnlRecord(TransactionType::Income, 4_000_00, '2026-10-02', ['category_id' => $web->id]);
    pnlRecord(TransactionType::Income, 1_000_00, '2026-10-03', ['category_id' => $maintenance->id]);
    pnlRecord(TransactionType::Expense, 300_00, '2026-10-04', ['category_id' => $hosting->id]);

    $report = $this->report->report(Period::resolve('this_month'));

    expect($report['income_rows'])->toHaveCount(2)
        ->and($report['income_rows'][0]['name'])->toBe($web->name)
        ->and($report['income_rows'][0]['amount'])->toBe(4_000_00)
        ->and($report['income_rows'][0]['share'])->toBe(80.0)
        ->and($report['income_rows'][1]['share'])->toBe(20.0)
        ->and($report['expense_rows'])->toHaveCount(1)
        ->and($report['expense_rows'][0]['share'])->toBe(100.0);
});

it('labels anything with no category', function () {
    pnlRecord(TransactionType::Expense, 50_00, '2026-10-02');

    $report = $this->report->report(Period::resolve('this_month'));

    expect($report['expense_rows'][0]['name'])->toBe(__('report.uncategorised'));
});

it('gives a year twelve month rows', function () {
    pnlRecord(TransactionType::Income, 1_000_00, '2026-03-15');
    pnlRecord(TransactionType::Expense, 400_00, '2026-03-20');

    $report = $this->report->report(Period::resolve('this_year'));

    expect($report['months'])->toHaveCount(12)
        ->and($report['months'][2]['month'])->toBe('2026-03-01')
        ->and($report['months'][2]['income'])->toBe(1_000_00)
        ->and($report['months'][2]['expense'])->toBe(400_00)
        ->and($report['months'][2]['net'])->toBe(600_00)
        // The quiet months still get a row.
        ->and($report['months'][0])->toBe([
            'month' => '2026-01-01', 'income' => 0, 'expense' => 0, 'net' => 0,
        ]);
});

it('leaves anything outside the window out', function () {
    pnlRecord(TransactionType::Income, 9_999_00, '2026-09-30');
    pnlRecord(TransactionType::Income, 100_00, '2026-10-01');

    expect($this->report->report(Period::resolve('this_month'))['income'])
        ->toBe(100_00);
});

it('reports a loss as a negative profit', function () {
    pnlRecord(TransactionType::Income, 500_00, '2026-10-02');
    pnlRecord(TransactionType::Expense, 1_300_00, '2026-10-03');

    expect($this->report->report(Period::resolve('this_month'))['net'])
        ->toBe(-800_00);
});

it('reports nothing for an empty window', function () {
    $report = $this->report->report(Period::resolve('last_month'));

    expect($report['income'])->toBe(0)
        ->and($report['income_rows'])->toBe([])
        ->and($report['months'])->toHaveCount(1);
});

it('splits a category only by the side it belongs to', function () {
    $others = Category::query()
        ->where('type', CategoryType::Expense)
        ->whereJsonContains('name->en', 'Others')
        ->firstOrFail();

    pnlRecord(TransactionType::Expense, 120_00, '2026-10-02', ['category_id' => $others->id]);
    pnlRecord(TransactionType::Income, 800_00, '2026-10-03');

    $report = $this->report->report(Period::resolve('this_month'));

    expect($report['expense_rows'])->toHaveCount(1)
        ->and($report['expense_rows'][0]['name'])->toBe($others->name)
        ->and($report['income_rows'])->toHaveCount(1)
        ->and($report['income_rows'][0]['name'])->toBe(__('report.uncategorised'));
});
