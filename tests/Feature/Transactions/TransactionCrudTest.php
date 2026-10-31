<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Fund;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\FundBalanceService;
use Database\Seeders\FundSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FundSeeder::class);
    $this->actingAs(User::factory()->create());

    $this->account = Account::factory()->create(['name' => 'Maybank']);
    $this->other = Account::factory()->create(['name' => 'Wallet']);
});

function fundBalance(string $slug): int
{
    $fund = Fund::query()->where('slug', $slug)->firstOrFail();

    return app(FundBalanceService::class)->for($fund);
}

/** @param array<string, mixed> $overrides */
function payload(array $overrides = []): array
{
    return [
        'type' => 'income',
        'account_id' => test()->account->id,
        'to_account_id' => null,
        'amount' => '3000',
        'date' => '2026-10-30',
        'description' => 'Projek web',
        'reference' => null,
        'category_id' => null,
        'fund_id' => null,
        'tax_deductible' => false,
        ...$overrides,
    ];
}

it('lists transactions with the form options', function () {
    Transaction::factory()->count(3)->create(['account_id' => $this->account->id]);

    $this->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('transactions.data', 3)
            ->has('formOptions.types', 5)
            ->has('formOptions.accounts', 2)
            ->has('formOptions.funds', 5)
            ->where('filters.type', null),
        );
});

it('records an income in ringgit and splits it across the funds', function () {
    $this->from(route('transactions.index'))
        ->post(route('transactions.store'), payload())
        ->assertRedirect(route('transactions.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $transaction = Transaction::query()->firstOrFail();

    expect($transaction->amount)->toBe(300_000)
        ->and($transaction->type)->toBe(TransactionType::Income)
        ->and($transaction->fund_id)->toBeNull()
        ->and(fundBalance('operating'))->toBe(90_000)
        ->and(fundBalance('owner_pay'))->toBe(120_000)
        ->and(app(AccountBalanceService::class)->for($this->account))->toBe(300_000);
});

it('takes an expense from the fund its category points at', function () {
    $hardware = Category::factory()->create([
        'default_fund_id' => Fund::query()->where('slug', 'capital')->value('id'),
        'tax_deductible_default' => true,
    ]);

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
        'category_id' => $hardware->id,
        'tax_deductible' => true,
    ]));

    $transaction = Transaction::query()->firstOrFail();

    expect($transaction->fund_id)->toBe($hardware->default_fund_id)
        ->and($transaction->tax_deductible)->toBeTrue()
        ->and(fundBalance('capital'))->toBe(-5_000);
});

it('falls back to the operating fund for an uncategorised expense', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
    ]));

    expect(fundBalance('operating'))->toBe(-5_000);
});

it('always takes an owner draw from the owner pay fund', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'owner_draw',
        // Even when another fund is asked for.
        'fund_id' => Fund::query()->where('slug', 'tax')->value('id'),
        'amount' => '1200',
    ]));

    expect(fundBalance('owner_pay'))->toBe(-120_000)
        ->and(fundBalance('tax'))->toBe(0);
});

it('moves money between accounts without touching the funds', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'transfer',
        'to_account_id' => $this->other->id,
        'amount' => '500',
    ]));

    $balances = app(AccountBalanceService::class);

    expect($balances->for($this->account))->toBe(-50_000)
        ->and($balances->for($this->other))->toBe(50_000)
        ->and(app(FundBalanceService::class)->total())->toBe(0);
});

it('rejects a transfer to the same account', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'transfer',
        'to_account_id' => $this->account->id,
        'amount' => '500',
    ]))->assertSessionHasErrors('to_account_id');
});

it('requires a destination account for a transfer', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'transfer',
        'to_account_id' => null,
    ]))->assertSessionHasErrors('to_account_id');
});

it('rejects an amount of zero', function () {
    $this->from(route('transactions.index'))
        ->post(route('transactions.store'), payload(['amount' => '0']))
        ->assertSessionHasErrors('amount');
});

it('rejects a category of the wrong kind', function () {
    $expenseCategory = Category::factory()->create();

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'income',
        'category_id' => $expenseCategory->id,
    ]))->assertSessionHasErrors('category_id');
});

it('drops the category when the type cannot carry one', function () {
    $category = Category::factory()->income()->create();

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'capital_injection',
        'category_id' => $category->id,
    ]));

    expect(Transaction::query()->firstOrFail()->category_id)->toBeNull();
});

it('rebuilds the split when the amount is edited', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload());

    $transaction = Transaction::query()->firstOrFail();

    $this->from(route('transactions.index'))
        ->post(route('transactions.update', $transaction), payload(['amount' => '2000']))
        ->assertRedirect(route('transactions.index'));

    expect(fundBalance('operating'))->toBe(60_000)
        ->and(fundBalance('owner_pay'))->toBe(80_000)
        ->and($transaction->refresh()->fundMovements()->count())->toBe(5);
});

it('rebuilds the split when the type changes', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload());

    $transaction = Transaction::query()->firstOrFail();

    $this->from(route('transactions.index'))->post(route('transactions.update', $transaction), payload([
        'type' => 'expense',
        'amount' => '3000',
    ]));

    expect(fundBalance('operating'))->toBe(-300_000)
        ->and(fundBalance('owner_pay'))->toBe(0)
        ->and($transaction->refresh()->fundMovements()->count())->toBe(1);
});

it('removes the fund movements when a transaction is deleted', function () {
    $this->from(route('transactions.index'))->post(route('transactions.store'), payload());

    $transaction = Transaction::query()->firstOrFail();

    $this->from(route('transactions.index'))
        ->delete(route('transactions.destroy', $transaction))
        ->assertRedirect(route('transactions.index'));

    expect(app(FundBalanceService::class)->total())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(Transaction::withTrashed()->count())->toBe(1)
        ->and(app(AccountBalanceService::class)->for($this->account))->toBe(0);
});

it('stores a receipt on the private disk', function () {
    Storage::fake('local');

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
        'receipt' => UploadedFile::fake()->image('resit.png'),
    ]));

    $path = Transaction::query()->firstOrFail()->receipt_path;

    expect($path)->toBeString();
    Storage::disk('local')->assertExists($path);
});

it('rejects a receipt that is not an image or pdf', function () {
    Storage::fake('local');

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
        'receipt' => UploadedFile::fake()->create('resit.exe', 10, 'application/x-msdownload'),
    ]))->assertSessionHasErrors('receipt');
});

it('rejects a receipt over 5MB', function () {
    Storage::fake('local');

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
        'receipt' => UploadedFile::fake()->create('resit.pdf', 5_121, 'application/pdf'),
    ]))->assertSessionHasErrors('receipt');
});

it('replaces the old receipt on re-upload', function () {
    Storage::fake('local');

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
        'receipt' => UploadedFile::fake()->image('first.png'),
    ]));

    $transaction = Transaction::query()->firstOrFail();
    $first = $transaction->receipt_path;

    $this->from(route('transactions.index'))->post(route('transactions.update', $transaction), payload([
        'type' => 'expense',
        'amount' => '50',
        'receipt' => UploadedFile::fake()->image('second.png'),
    ]));

    $second = $transaction->refresh()->receipt_path;

    expect($second)->not->toBe($first);
    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($second);
});

it('removes the receipt on request', function () {
    Storage::fake('local');

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
        'receipt' => UploadedFile::fake()->image('resit.png'),
    ]));

    $transaction = Transaction::query()->firstOrFail();
    $path = $transaction->receipt_path;

    $this->from(route('transactions.index'))->post(route('transactions.update', $transaction), payload([
        'type' => 'expense',
        'amount' => '50',
        'remove_receipt' => true,
    ]));

    expect($transaction->refresh()->receipt_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('serves a receipt to the signed-in owner', function () {
    Storage::fake('local');

    $this->from(route('transactions.index'))->post(route('transactions.store'), payload([
        'type' => 'expense',
        'amount' => '50',
        'receipt' => UploadedFile::fake()->image('resit.png'),
    ]));

    $transaction = Transaction::query()->firstOrFail();

    $this->get(route('transactions.receipt', $transaction))->assertOk();
});

it('404s when a transaction has no receipt', function () {
    $transaction = Transaction::factory()->create(['account_id' => $this->account->id]);

    $this->get(route('transactions.receipt', $transaction))->assertNotFound();
});
