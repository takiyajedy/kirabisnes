<?php

use App\Actions\Transactions\StoreTransaction;
use App\Enums\Locale;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Fund;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OwnerPayService;
use Carbon\CarbonImmutable;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);

    $this->account = Account::factory()->create(['opening_balance' => 0]);
    $this->ownerPay = app(OwnerPayService::class);
    $this->actingAs(User::factory()->create(['locale' => Locale::Malay]));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function income(int $sen, string $date): Transaction
{
    return app(StoreTransaction::class)->handle([
        'type' => TransactionType::Income->value,
        'account_id' => test()->account->id,
        'amount' => $sen,
        'date' => $date,
        'description' => 'Projek',
    ]);
}

function draw(int $sen, string $date): Transaction
{
    return app(StoreTransaction::class)->handle([
        'type' => TransactionType::OwnerDraw->value,
        'account_id' => test()->account->id,
        'amount' => $sen,
        'date' => $date,
        'description' => 'Gaji sendiri',
    ]);
}

it('reads the balance of the owner pay fund', function () {
    income(3_000_00, '2026-10-02');

    // 40% of RM3,000, per the default split.
    expect($this->ownerPay->balance())->toBe(1_200_00);
});

it('averages income over three calendar months', function () {
    income(3_000_00, '2026-08-05');
    income(1_500_00, '2026-09-10');
    income(1_500_00, '2026-10-01');

    // (3000 + 1500 + 1500) / 3
    expect($this->ownerPay->averageMonthlyIncome())->toBe(2_000_00);
});

it('ignores income older than the three months', function () {
    income(9_000_00, '2026-07-31');
    income(3_000_00, '2026-10-01');

    expect($this->ownerPay->averageMonthlyIncome())->toBe(1_000_00);
});

it('suggests the share of the average when the fund holds more than that', function () {
    // Three good months fill the fund well past one month's worth.
    income(3_000_00, '2026-08-05');
    income(3_000_00, '2026-09-05');
    income(3_000_00, '2026-10-05');

    // Average RM3,000, the fund's share is 40%, so RM1,200 —
    // and the fund itself holds 40% of RM9,000, which is RM3,600.
    expect($this->ownerPay->balance())->toBe(3_600_00)
        ->and($this->ownerPay->safeDraw())->toBe(1_200_00);
});

it('suggests no more than the fund actually holds', function () {
    // One big month, then the money is drawn out again.
    income(9_000_00, '2026-10-02');
    draw(3_500_00, '2026-10-03');

    // The fund has RM100 left of its RM3,600 share, and the average says
    // RM1,200 — a suggestion of RM1,200 would overdraw it.
    expect($this->ownerPay->balance())->toBe(100_00)
        ->and($this->ownerPay->averageMonthlyIncome())->toBe(3_000_00)
        ->and($this->ownerPay->safeDraw())->toBe(100_00);
});

it('suggests nothing when the fund is overdrawn', function () {
    income(1_000_00, '2026-10-02');
    draw(900_00, '2026-10-03');

    expect($this->ownerPay->balance())->toBe(-500_00)
        ->and($this->ownerPay->safeDraw())->toBe(0);
});

it('follows the fund percentage if it is changed', function () {
    income(3_000_00, '2026-10-02');
    Fund::query()->where('slug', 'owner_pay')->update(['percentage' => 25]);

    // One month of income in the three month window, so the average is
    // RM1,000; a quarter of that is RM250. The fund itself still holds the
    // RM1,200 it was given under the old 40% share, so the average decides.
    expect($this->ownerPay->averageMonthlyIncome())->toBe(1_000_00)
        ->and($this->ownerPay->safeDraw())->toBe(250_00);
});

it('adds up what has been drawn this year only', function () {
    income(20_000_00, '2026-01-02');
    draw(1_000_00, '2026-03-05');
    draw(1_500_00, '2026-09-05');
    draw(9_999_00, '2025-12-31');

    expect($this->ownerPay->drawnThisYear())->toBe(2_500_00);
});

it('lists the draws newest first and leaves other transactions out', function () {
    income(20_000_00, '2026-01-02');
    draw(100_00, '2026-02-01');
    draw(200_00, '2026-08-01');

    $history = $this->ownerPay->history();

    expect($history->total())->toBe(2)
        ->and($history->items()[0]->amount)->toBe(200_00);
});

it('renders the page with the suggestion and its workings', function () {
    income(3_000_00, '2026-10-02');

    $this->get(route('owner-pay.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('OwnerPay/Index')
            ->where('fund.slug', 'owner_pay')
            ->where('fund.name', 'Gaji Sendiri')
            ->where('fund.balance', 1_200_00)
            ->where('suggestion.amount', 400_00)
            ->where('suggestion.average_income', 1_000_00)
            ->where('suggestion.months', 3)
            ->where('suggestion.percentage', 40)
            ->where('drawnThisYear', 0)
            ->where('year', 2026)
            ->has('history.data', 0)
            ->has('formOptions.types', 5),
        );
});

it('shows the draws it has already recorded', function () {
    income(3_000_00, '2026-10-02');
    draw(500_00, '2026-10-10');

    $this->get(route('owner-pay.index'))
        ->assertInertia(fn ($page) => $page
            ->where('drawnThisYear', 500_00)
            ->has('history.data', 1)
            ->where('history.data.0.amount', 500_00)
            ->where('history.data.0.type', 'owner_draw'),
        );
});

it('records a draw against the owner pay fund without being told which fund', function () {
    income(3_000_00, '2026-10-02');

    // This is what the "Ambil Gaji" button posts: ringgit, per section 3.1.
    $this->post(route('transactions.store'), [
        'type' => 'owner_draw',
        'account_id' => $this->account->id,
        'amount' => '400.00',
        'date' => '2026-10-15',
        'description' => 'Gaji sendiri',
    ])->assertRedirect();

    expect($this->ownerPay->balance())->toBe(800_00)
        ->and($this->ownerPay->drawnThisYear())->toBe(400_00);
});

it('is closed to guests', function () {
    auth()->logout();

    $this->get(route('owner-pay.index'))->assertRedirect(route('login'));
});
