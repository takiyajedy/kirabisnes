<?php

use App\Enums\Locale;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    $this->seed(FundSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('previews the split the form shows for an income', function () {
    // PLAN.md: "RM3,000 -> Operasi RM900, Gaji Sendiri RM1,200, ..."
    // The amount goes over the wire in ringgit, like every other endpoint
    // that takes money (PLAN.md 3.1).
    $response = $this->getJson(route('transactions.allocation-preview', ['amount' => '3000.00']));

    $response->assertOk()
        ->assertJsonPath('amount', 300_000)
        ->assertJsonCount(5, 'shares')
        ->assertJsonPath('shares.0.name', 'Operasi')
        ->assertJsonPath('shares.0.amount', 90_000)
        ->assertJsonPath('shares.1.amount', 120_000);

    expect(collect($response->json('shares'))->sum('amount'))->toBe(300_000);
});

it('previews the same figures the allocation service would record', function () {
    $response = $this->getJson(route('transactions.allocation-preview', ['amount' => '100.01']));

    expect($response->json('shares.0.amount'))->toBe(3_001)
        ->and(collect($response->json('shares'))->sum('amount'))->toBe(10_001);
});

it('answers in the language the owner uses', function () {
    // For a signed-in user, users.locale wins over the cookie (PLAN.md 3.5).
    $this->user->update(['locale' => Locale::English]);

    $this->getJson(route('transactions.allocation-preview', ['amount' => '1000']))
        ->assertOk()
        ->assertJsonPath('shares.0.name', 'Operating');
});

it('returns zero shares for a zero amount', function () {
    $this->getJson(route('transactions.allocation-preview', ['amount' => 0]))
        ->assertOk()
        ->assertJsonPath('shares.0.amount', 0);
});

it('treats a negative amount as zero', function () {
    $this->getJson(route('transactions.allocation-preview', ['amount' => '-5.00']))
        ->assertOk()
        ->assertJsonPath('amount', 0);
});

it('leaves switched-off funds out of the preview', function () {
    Fund::query()->where('slug', 'emergency')->update(['is_active' => false]);

    $this->getJson(route('transactions.allocation-preview', ['amount' => '1000']))
        ->assertOk()
        ->assertJsonCount(4, 'shares');
});

it('serves the form options for the quick dialog', function () {
    Account::factory()->create();

    $this->getJson(route('transactions.options'))
        ->assertOk()
        ->assertJsonCount(5, 'types')
        ->assertJsonCount(1, 'accounts')
        ->assertJsonCount(5, 'funds')
        ->assertJsonPath('types.0.value', 'income');
});

it('keeps guests out of both endpoints', function () {
    auth()->logout();

    $this->get(route('transactions.allocation-preview'))->assertRedirect(route('login'));
    $this->get(route('transactions.options'))->assertRedirect(route('login'));
});
