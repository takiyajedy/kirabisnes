<?php

use App\Models\Account;
use App\Models\Fund;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\FundSeeder;

/**
 * Forms send ringgit and the FormRequest converts it to sen (PLAN.md 3.1).
 *
 * Getting this backwards does not fail loudly: it stores an amount a hundred
 * times too large. These tests pin the contract down at every endpoint that
 * takes money, in the form the browser actually sends — a decimal string.
 */
beforeEach(function () {
    $this->seed(FundSeeder::class);
    $this->actingAs(User::factory()->create());
    $this->account = Account::factory()->create();
});

it('reads a transaction amount as ringgit', function () {
    $this->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '12.50',
        'date' => '2026-10-15',
        'description' => 'Kopi',
    ])->assertRedirect();

    expect(Transaction::query()->value('amount'))->toBe(1250);
});

it('does not treat a whole ringgit amount as sen', function () {
    $this->post(route('transactions.store'), [
        'type' => 'expense',
        'account_id' => $this->account->id,
        'amount' => '1250',
        'date' => '2026-10-15',
        'description' => 'Server',
    ])->assertRedirect();

    expect(Transaction::query()->value('amount'))->toBe(125_000);
});

it('reads an opening balance as ringgit', function () {
    $this->post(route('accounts.store'), [
        'name' => 'CIMB',
        'type' => 'bank',
        'opening_balance' => '1500.75',
        'is_active' => true,
        'sort_order' => 0,
    ])->assertRedirect();

    expect(Account::query()->where('name', 'CIMB')->value('opening_balance'))
        ->toBe(150_075);
});

it('reads a fund transfer amount as ringgit', function () {
    // Give the funds something to move first.
    $this->post(route('transactions.store'), [
        'type' => 'income',
        'account_id' => $this->account->id,
        'amount' => '3000',
        'date' => '2026-10-01',
        'description' => 'Projek',
    ])->assertRedirect();

    $from = Fund::query()->where('slug', 'operating')->firstOrFail();
    $to = Fund::query()->where('slug', 'tax')->firstOrFail();

    $this->post(route('funds.transfer'), [
        'from_fund_id' => $from->id,
        'to_fund_id' => $to->id,
        'amount' => '50.25',
        'date' => '2026-10-15',
    ])->assertRedirect();

    expect($from->movements()->orderByDesc('id')->value('amount'))->toBe(-5025)
        ->and($to->movements()->orderByDesc('id')->value('amount'))->toBe(5025);
});
