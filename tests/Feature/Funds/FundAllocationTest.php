<?php

use App\Enums\FundMovementType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Fund;
use App\Models\Transaction;
use App\Services\FundAllocationService;
use App\Services\FundBalanceService;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    $this->seed(FundSeeder::class);

    $this->allocation = app(FundAllocationService::class);
    $this->account = Account::factory()->create();
});

/** @return array<string, int> Fund slug => balance in sen. */
function balancesBySlug(): array
{
    $byId = app(FundBalanceService::class)->all();

    return Fund::query()->ordered()->get()
        ->mapWithKeys(fn (Fund $fund) => [$fund->slug => $byId[$fund->id] ?? 0])
        ->all();
}

/** @param array<string, mixed> $attributes */
function recordTransaction(TransactionType $type, int $sen, array $attributes = []): Transaction
{
    $transaction = Transaction::factory()->create([
        'type' => $type,
        'account_id' => test()->account->id,
        'amount' => $sen,
        ...$attributes,
    ]);

    app(FundAllocationService::class)->sync($transaction);

    return $transaction;
}

it('splits an income across the five funds by percentage', function () {
    // PLAN.md acceptance: RM3,000 income.
    recordTransaction(TransactionType::Income, 300_000);

    expect(balancesBySlug())->toBe([
        'operating' => 90_000,   // 30%
        'owner_pay' => 120_000,  // 40%
        'tax' => 30_000,         // 10%
        'emergency' => 30_000,   // 10%
        'capital' => 30_000,     // 10%
    ]);
});

it('gives the rounding remainder to the first fund', function () {
    // RM100.01 does not divide cleanly: 1 sen is left over.
    recordTransaction(TransactionType::Income, 10_001);

    $balances = balancesBySlug();

    expect($balances)->toBe([
        'operating' => 3_001,
        'owner_pay' => 4_000,
        'tax' => 1_000,
        'emergency' => 1_000,
        'capital' => 1_000,
    ])->and(array_sum($balances))->toBe(10_001);
});

it('never loses or invents a sen, for any amount', function (int $sen) {
    recordTransaction(TransactionType::Income, $sen);

    expect(array_sum(balancesBySlug()))->toBe($sen);
})->with([1, 2, 3, 7, 99, 101, 999, 1_234, 10_001, 33_333, 99_999, 1_000_003]);

it('records one allocate movement per fund', function () {
    $transaction = recordTransaction(TransactionType::Income, 300_000);

    expect($transaction->fundMovements()->count())->toBe(5)
        ->and($transaction->fundMovements()->pluck('type')->unique()->all())
        ->toBe([FundMovementType::Allocate]);
});

it('takes an expense out of the fund it names', function () {
    recordTransaction(TransactionType::Income, 300_000);

    $operating = Fund::query()->where('slug', 'operating')->first();

    // PLAN.md acceptance: RM50 expense against Operating.
    recordTransaction(TransactionType::Expense, 5_000, ['fund_id' => $operating->id]);

    expect(balancesBySlug()['operating'])->toBe(85_000);
});

it('takes an owner draw out of the owner pay fund', function () {
    recordTransaction(TransactionType::Income, 300_000);

    $ownerPay = Fund::query()->where('slug', 'owner_pay')->first();

    recordTransaction(TransactionType::OwnerDraw, 50_000, ['fund_id' => $ownerPay->id]);

    expect(balancesBySlug()['owner_pay'])->toBe(70_000);
});

it('puts a capital injection wholly into one fund', function () {
    $capital = Fund::query()->where('slug', 'capital')->first();

    recordTransaction(TransactionType::CapitalInjection, 500_000, ['fund_id' => $capital->id]);

    expect(balancesBySlug())->toBe([
        'operating' => 0,
        'owner_pay' => 0,
        'tax' => 0,
        'emergency' => 0,
        'capital' => 500_000,
    ]);
});

it('leaves every fund untouched for a transfer between accounts', function () {
    $other = Account::factory()->create();

    recordTransaction(TransactionType::Transfer, 100_000, [
        'to_account_id' => $other->id,
    ]);

    expect(array_sum(balancesBySlug()))->toBe(0);
});

it('recalculates the split when the amount is edited', function () {
    $transaction = recordTransaction(TransactionType::Income, 300_000);

    // PLAN.md acceptance: editing RM3,000 down to RM2,000.
    $transaction->update(['amount' => 200_000]);
    $this->allocation->sync($transaction->refresh());

    expect(balancesBySlug())->toBe([
        'operating' => 60_000,
        'owner_pay' => 80_000,
        'tax' => 20_000,
        'emergency' => 20_000,
        'capital' => 20_000,
    ])->and($transaction->fundMovements()->count())->toBe(5);
});

it('clears the movements when a transaction is removed', function () {
    $transaction = recordTransaction(TransactionType::Income, 300_000);

    $this->allocation->clear($transaction);

    expect(array_sum(balancesBySlug()))->toBe(0);
});

it('lets a fund go negative rather than blocking the expense', function () {
    $operating = Fund::query()->where('slug', 'operating')->first();

    recordTransaction(TransactionType::Expense, 25_000, ['fund_id' => $operating->id]);

    expect(balancesBySlug()['operating'])->toBe(-25_000);
});

it('skips funds that are switched off', function () {
    Fund::query()->where('slug', 'emergency')->update(['is_active' => false]);
    // Keep the active percentages adding up to 100.
    Fund::query()->where('slug', 'tax')->update(['percentage' => 20]);

    recordTransaction(TransactionType::Income, 100_000);

    $balances = balancesBySlug();

    expect($balances['emergency'])->toBe(0)
        ->and($balances['tax'])->toBe(20_000)
        ->and(array_sum($balances))->toBe(100_000);
});

it('splits nothing when the amount is zero', function () {
    $transaction = recordTransaction(TransactionType::Income, 0);

    expect($transaction->fundMovements()->count())->toBe(0);
});

it('splits by percentage without touching the database', function () {
    $funds = Fund::query()->active()->ordered()->get();

    expect(FundAllocationService::split(300_000, $funds))
        ->toBe($funds->mapWithKeys(fn (Fund $f) => [
            $f->id => (int) (300_000 * (float) $f->percentage / 100),
        ])->all());
});
