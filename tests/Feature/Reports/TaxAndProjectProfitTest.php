<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\ProjectProfitService;
use App\Services\TaxSummaryService;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);

    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->tax = app(TaxSummaryService::class);
    $this->profit = app(ProjectProfitService::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function taxRecord(TransactionType $type, int $sen, string $date, array $attributes = []): void
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

// --- tax summary ---------------------------------------------------------

it('counts only deductible expenses against income', function () {
    taxRecord(TransactionType::Income, 100_000_00, '2026-03-01');
    taxRecord(TransactionType::Expense, 20_000_00, '2026-04-01', ['tax_deductible' => true]);
    taxRecord(TransactionType::Expense, 5_000_00, '2026-05-01', ['tax_deductible' => false]);

    $report = $this->tax->report(2026);

    expect($report['income'])->toBe(100_000_00)
        ->and($report['expense'])->toBe(25_000_00)
        ->and($report['deductible'])->toBe(20_000_00)
        ->and($report['non_deductible'])->toBe(5_000_00)
        // Only the claimable side comes off.
        ->and($report['taxable'])->toBe(80_000_00);
});

it('leaves owner draws and capital out of the tax figures', function () {
    taxRecord(TransactionType::Income, 50_000_00, '2026-03-01');
    taxRecord(TransactionType::OwnerDraw, 30_000_00, '2026-06-01');
    taxRecord(TransactionType::CapitalInjection, 10_000_00, '2026-07-01');

    $report = $this->tax->report(2026);

    expect($report['income'])->toBe(50_000_00)
        ->and($report['taxable'])->toBe(50_000_00);
});

it('keeps each year to itself', function () {
    taxRecord(TransactionType::Income, 10_000_00, '2025-12-31');
    taxRecord(TransactionType::Income, 20_000_00, '2026-01-01');

    expect($this->tax->report(2026)['income'])->toBe(20_000_00)
        ->and($this->tax->report(2025)['income'])->toBe(10_000_00);
});

it('reports a loss year as negative taxable income', function () {
    taxRecord(TransactionType::Income, 5_000_00, '2026-03-01');
    taxRecord(TransactionType::Expense, 9_000_00, '2026-04-01', ['tax_deductible' => true]);

    expect($this->tax->report(2026)['taxable'])->toBe(-4_000_00);
});

it('shows what has been set aside in the tax fund', function () {
    // The default split puts 10% of income into the tax fund.
    taxRecord(TransactionType::Income, 100_000_00, '2026-03-01');

    expect($this->tax->report(2026)['tax_fund'])->toBe(10_000_00);
});

it('splits the deductible expenses by category', function () {
    $hosting = Category::query()->whereJsonContains('name->en', 'Hosting & Server')->firstOrFail();
    $fuel = Category::query()->whereJsonContains('name->en', 'Fuel / Toll / Parking')->firstOrFail();

    taxRecord(TransactionType::Income, 50_000_00, '2026-02-01');
    taxRecord(TransactionType::Expense, 1_200_00, '2026-03-01', [
        'category_id' => $hosting->id, 'tax_deductible' => true,
    ]);
    taxRecord(TransactionType::Expense, 3_000_00, '2026-04-01', [
        'category_id' => $fuel->id, 'tax_deductible' => true,
    ]);

    $rows = $this->tax->report(2026)['deductible_rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['amount'])->toBe(3_000_00)
        ->and($rows[0]['name'])->toBe($fuel->name);
});

it('offers the years that have transactions, plus this one', function () {
    taxRecord(TransactionType::Income, 100_00, '2024-05-01');
    taxRecord(TransactionType::Income, 100_00, '2025-05-01');

    expect($this->tax->availableYears())->toBe([2026, 2025, 2024]);
});

it('offers this year even with nothing recorded', function () {
    expect($this->tax->availableYears())->toBe([2026]);
});

// --- project profit ------------------------------------------------------

it('takes your own time off the project profit', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Portal',
        // RM80 an hour.
        'internal_hourly_rate' => 80_00,
    ]);

    taxRecord(TransactionType::Income, 10_000_00, '2026-05-01', ['project_id' => $project->id]);
    taxRecord(TransactionType::Expense, 1_000_00, '2026-05-02', ['project_id' => $project->id]);

    // 30 hours logged.
    TimeEntry::factory()->lasting(1_800)->create(['project_id' => $project->id]);

    $rows = $this->profit->report();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['received'])->toBe(10_000_00)
        ->and($rows[0]['costs'])->toBe(1_000_00)
        ->and($rows[0]['minutes'])->toBe(1_800)
        // 30 hours x RM80
        ->and($rows[0]['labour'])->toBe(2_400_00)
        ->and($rows[0]['profit'])->toBe(6_600_00)
        ->and($rows[0]['margin'])->toBe(66.0);
});

it('counts a part hour as part of the rate', function () {
    $project = Project::factory()->create(['internal_hourly_rate' => 90_00]);

    taxRecord(TransactionType::Income, 1_000_00, '2026-05-01', ['project_id' => $project->id]);
    // 90 minutes at RM90 an hour is RM135.
    TimeEntry::factory()->lasting(90)->create(['project_id' => $project->id]);

    expect($this->profit->report()[0]['labour'])->toBe(135_00);
});

it('leaves unbillable time out of the cost', function () {
    $project = Project::factory()->create(['internal_hourly_rate' => 60_00]);

    taxRecord(TransactionType::Income, 1_000_00, '2026-05-01', ['project_id' => $project->id]);
    TimeEntry::factory()->lasting(60)->create(['project_id' => $project->id]);
    TimeEntry::factory()->lasting(600)->unbillable()->create(['project_id' => $project->id]);

    $rows = $this->profit->report();

    expect($rows[0]['minutes'])->toBe(60)
        ->and($rows[0]['labour'])->toBe(60_00);
});

it('leaves a running timer out of the cost', function () {
    $project = Project::factory()->create(['internal_hourly_rate' => 60_00]);

    taxRecord(TransactionType::Income, 1_000_00, '2026-05-01', ['project_id' => $project->id]);
    TimeEntry::factory()->running()->create(['project_id' => $project->id]);

    // Nothing has been decided yet, so the figure does not move.
    expect($this->profit->report()[0]['minutes'])->toBe(0)
        ->and($this->profit->report()[0]['labour'])->toBe(0);
});

it('charges no labour when a project has no rate', function () {
    $project = Project::factory()->create(['internal_hourly_rate' => null]);

    taxRecord(TransactionType::Income, 2_000_00, '2026-05-01', ['project_id' => $project->id]);
    TimeEntry::factory()->lasting(600)->create(['project_id' => $project->id]);

    $rows = $this->profit->report();

    expect($rows[0]['labour'])->toBe(0)
        ->and($rows[0]['profit'])->toBe(2_000_00);
});

it('has no margin to report for a project with no money in', function () {
    Project::factory()->create(['internal_hourly_rate' => 50_00]);

    expect($this->profit->report()[0]['margin'])->toBeNull();
});

it('lists the worst margin first', function () {
    $good = Project::factory()->create(['name' => 'Baik', 'internal_hourly_rate' => 50_00]);
    $bad = Project::factory()->create(['name' => 'Teruk', 'internal_hourly_rate' => 50_00]);

    taxRecord(TransactionType::Income, 10_000_00, '2026-05-01', ['project_id' => $good->id]);
    taxRecord(TransactionType::Income, 1_000_00, '2026-05-02', ['project_id' => $bad->id]);
    taxRecord(TransactionType::Expense, 900_00, '2026-05-03', ['project_id' => $bad->id]);

    $names = array_column($this->profit->report(), 'name');

    expect($names)->toBe(['Teruk', 'Baik']);
});

it('reports a project that lost money as a negative margin', function () {
    $project = Project::factory()->create(['internal_hourly_rate' => 100_00]);

    taxRecord(TransactionType::Income, 1_000_00, '2026-05-01', ['project_id' => $project->id]);
    // 20 hours at RM100 is RM2,000 of time against RM1,000 of income.
    TimeEntry::factory()->lasting(1_200)->create(['project_id' => $project->id]);

    $rows = $this->profit->report();

    expect($rows[0]['profit'])->toBe(-1_000_00)
        ->and($rows[0]['margin'])->toBe(-100.0);
});
