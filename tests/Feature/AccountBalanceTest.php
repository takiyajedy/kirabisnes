<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountBalanceService;

beforeEach(function () {
    $this->balances = app(AccountBalanceService::class);
});

/** @param array<string, mixed> $attributes */
function post(Account $account, TransactionType $type, int $sen, array $attributes = []): Transaction
{
    return Transaction::factory()->create([
        'type' => $type,
        'account_id' => $account->id,
        'amount' => $sen,
        ...$attributes,
    ]);
}

it('starts at the opening balance', function () {
    $account = Account::factory()->withOpeningBalance(50_000)->create();

    expect($this->balances->for($account))->toBe(50_000);
});

it('adds income and capital injections', function () {
    $account = Account::factory()->withOpeningBalance(10_000)->create();

    post($account, TransactionType::Income, 300_000);
    post($account, TransactionType::CapitalInjection, 100_000);

    expect($this->balances->for($account))->toBe(410_000);
});

it('subtracts expenses and owner draws', function () {
    $account = Account::factory()->withOpeningBalance(500_000)->create();

    post($account, TransactionType::Expense, 5_000);
    post($account, TransactionType::OwnerDraw, 120_000);

    expect($this->balances->for($account))->toBe(375_000);
});

it('moves money between accounts on a transfer', function () {
    $from = Account::factory()->withOpeningBalance(200_000)->create();
    $to = Account::factory()->withOpeningBalance(0)->create();

    post($from, TransactionType::Transfer, 75_000, ['to_account_id' => $to->id]);

    expect($this->balances->for($from))->toBe(125_000)
        ->and($this->balances->for($to))->toBe(75_000);
});

it('keeps the total unchanged across a transfer', function () {
    $from = Account::factory()->withOpeningBalance(200_000)->create();
    $to = Account::factory()->withOpeningBalance(50_000)->create();

    post($from, TransactionType::Transfer, 75_000, ['to_account_id' => $to->id]);

    expect($this->balances->total())->toBe(250_000);
});

it('ignores a deleted transaction', function () {
    $account = Account::factory()->withOpeningBalance(0)->create();

    $transaction = post($account, TransactionType::Income, 300_000);

    expect($this->balances->for($account))->toBe(300_000);

    $transaction->delete();

    expect(app(AccountBalanceService::class)->for($account))->toBe(0);
});

it('can go negative', function () {
    $account = Account::factory()->withOpeningBalance(1_000)->create();

    post($account, TransactionType::Expense, 5_000);

    expect($this->balances->for($account))->toBe(-4_000);
});

it('totals every account in one pass', function () {
    $a = Account::factory()->withOpeningBalance(100_00)->create();
    $b = Account::factory()->withOpeningBalance(200_00)->create();

    post($a, TransactionType::Income, 50_00);
    post($b, TransactionType::Expense, 20_00);

    expect($this->balances->all())->toBe([
        $a->id => 150_00,
        $b->id => 180_00,
    ])->and($this->balances->total())->toBe(330_00);
});

it('leaves inactive accounts out of the active total', function () {
    $active = Account::factory()->withOpeningBalance(100_00)->create();
    Account::factory()->withOpeningBalance(900_00)->create(['is_active' => false]);

    expect($this->balances->totalActive())->toBe(100_00)
        ->and($this->balances->total())->toBe(1_000_00)
        ->and($this->balances->for($active))->toBe(100_00);
});
