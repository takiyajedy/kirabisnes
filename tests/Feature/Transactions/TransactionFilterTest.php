<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->maybank = Account::factory()->create(['name' => 'Maybank']);
    $this->wallet = Account::factory()->create(['name' => 'Wallet']);
    $this->hosting = Category::factory()->create();

    $this->webProject = Transaction::factory()->create([
        'type' => TransactionType::Income,
        'account_id' => $this->maybank->id,
        'date' => '2026-01-15',
        'description' => 'Projek web Kedai Ali',
        'amount' => 300_000,
    ]);

    $this->serverBill = Transaction::factory()->create([
        'type' => TransactionType::Expense,
        'account_id' => $this->wallet->id,
        'category_id' => $this->hosting->id,
        'date' => '2026-06-01',
        'description' => 'Bil server',
        'reference' => 'INV-998',
        'amount' => 12_000,
    ]);
});

/** @return array<int, string> The descriptions returned by the index. */
function descriptions(array $query = []): array
{
    $response = test()->get(route('transactions.index', $query))->assertOk();

    return collect($response->viewData('page')['props']['transactions']['data'])
        ->pluck('description')
        ->all();
}

it('shows everything when nothing is filtered', function () {
    expect(descriptions())->toHaveCount(2);
});

it('filters by type', function () {
    expect(descriptions(['type' => 'income']))->toBe(['Projek web Kedai Ali'])
        ->and(descriptions(['type' => 'expense']))->toBe(['Bil server']);
});

it('ignores an unknown type rather than returning nothing', function () {
    $this->get(route('transactions.index', ['type' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.type', null)
            ->has('transactions.data', 2),
        );
});

it('filters by account, including transfers into it', function () {
    expect(descriptions(['account_id' => $this->maybank->id]))
        ->toBe(['Projek web Kedai Ali']);

    Transaction::factory()->create([
        'type' => TransactionType::Transfer,
        'account_id' => $this->wallet->id,
        'to_account_id' => $this->maybank->id,
        'date' => '2026-07-01',
        'description' => 'Pindah ke Maybank',
        'amount' => 10_000,
    ]);

    expect(descriptions(['account_id' => $this->maybank->id]))
        ->toContain('Pindah ke Maybank');
});

it('filters by category', function () {
    expect(descriptions(['category_id' => $this->hosting->id]))->toBe(['Bil server']);
});

it('filters by a date range', function () {
    expect(descriptions(['from' => '2026-05-01']))->toBe(['Bil server'])
        ->and(descriptions(['to' => '2026-05-01']))->toBe(['Projek web Kedai Ali'])
        ->and(descriptions(['from' => '2026-01-01', 'to' => '2026-12-31']))
        ->toHaveCount(2);
});

it('searches the description and the reference', function () {
    expect(descriptions(['search' => 'Kedai']))->toBe(['Projek web Kedai Ali'])
        ->and(descriptions(['search' => 'INV-998']))->toBe(['Bil server'])
        ->and(descriptions(['search' => 'tiada']))->toBe([]);
});

it('combines filters', function () {
    expect(descriptions(['type' => 'expense', 'account_id' => $this->maybank->id]))
        ->toBe([]);
});

it('returns the newest transaction first', function () {
    expect(descriptions())->toBe(['Bil server', 'Projek web Kedai Ali']);
});

it('keeps the filters in the pagination links', function () {
    Transaction::factory()->count(30)->create([
        'type' => TransactionType::Income,
        'account_id' => $this->maybank->id,
    ]);

    $this->get(route('transactions.index', ['type' => 'income']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transactions.data', 25)
            ->where('transactions.meta.total', 31)
            ->where('filters.type', 'income'),
        );
});
