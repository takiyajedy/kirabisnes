<?php

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists accounts with their current balance', function () {
    $account = Account::factory()->withOpeningBalance(100_00)->create(['name' => 'Maybank']);

    Transaction::factory()->create([
        'type' => TransactionType::Income,
        'account_id' => $account->id,
        'amount' => 50_00,
    ]);

    $this->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Accounts/Index')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Maybank')
            ->where('accounts.0.balance', 150_00)
            ->where('totalBalance', 150_00)
            ->has('accountTypes', 3),
        );
});

it('creates an account, taking the opening balance in ringgit', function () {
    $this->post(route('accounts.store'), [
        'name' => 'CIMB Business',
        'type' => 'bank',
        'bank_name' => 'CIMB',
        'opening_balance' => '1250.50',
        'is_active' => true,
        'sort_order' => 0,
    ])->assertRedirect(route('accounts.index'));

    $account = Account::query()->where('name', 'CIMB Business')->first();

    expect($account->opening_balance)->toBe(125_050)
        ->and($account->type)->toBe(AccountType::Bank);
});

it('updates an account', function () {
    $account = Account::factory()->create(['name' => 'Old']);

    $this->patch(route('accounts.update', $account), [
        'name' => 'New',
        'type' => 'ewallet',
        'bank_name' => null,
        'opening_balance' => '0',
        'is_active' => false,
        'sort_order' => 3,
    ])->assertRedirect(route('accounts.index'));

    $account->refresh();

    expect($account->name)->toBe('New')
        ->and($account->type)->toBe(AccountType::EWallet)
        ->and($account->is_active)->toBeFalse();
});

it('requires a name and a known type', function () {
    $this->post(route('accounts.store'), [
        'name' => '',
        'type' => 'crypto',
        'opening_balance' => '0',
        'is_active' => true,
        'sort_order' => 0,
    ])->assertSessionHasErrors(['name', 'type']);
});

it('deletes an unused account', function () {
    $account = Account::factory()->create();

    $this->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(Account::query()->count())->toBe(0);
});

it('refuses to delete an account that has transactions', function () {
    $account = Account::factory()->create();

    Transaction::factory()->create([
        'account_id' => $account->id,
        'amount' => 1_000,
    ]);

    $this->delete(route('accounts.destroy', $account))
        ->assertRedirect(route('accounts.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Account::query()->count())->toBe(1);
});

it('refuses to delete an account that is a transfer destination', function () {
    $from = Account::factory()->create();
    $to = Account::factory()->create();

    Transaction::factory()->create([
        'type' => TransactionType::Transfer,
        'account_id' => $from->id,
        'to_account_id' => $to->id,
        'amount' => 1_000,
    ]);

    $this->delete(route('accounts.destroy', $to))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Account::query()->count())->toBe(2);
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get(route('accounts.index'))->assertRedirect(route('login'));
});
