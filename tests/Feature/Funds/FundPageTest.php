<?php

use App\Enums\FundMovementType;
use App\Models\Fund;
use App\Models\FundMovement;
use App\Models\User;
use App\Services\FundBalanceService;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    $this->seed(FundSeeder::class);
    $this->actingAs(User::factory()->create());
});

/** @return array<string, float|int> */
function sharePayload(array $overrides = []): array
{
    $funds = Fund::query()->ordered()->get()->map(fn (Fund $fund) => [
        'id' => $fund->id,
        'percentage' => (float) $fund->percentage,
        'is_active' => $fund->is_active,
    ])->all();

    foreach ($overrides as $slug => $percentage) {
        $index = Fund::query()->ordered()->pluck('slug')->search($slug);
        $funds[$index]['percentage'] = $percentage;
    }

    return ['funds' => $funds];
}

it('lists the funds with balances and shares', function () {
    $this->get(route('funds.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Funds/Index')
            ->has('funds', 5)
            ->where('funds.0.slug', 'operating')
            ->where('funds.0.percentage', 30)
            ->where('funds.0.balance', 0)
            ->where('totalBalance', 0),
        );
});

it('saves a new percentage split that adds up to 100', function () {
    $this->from(route('funds.index'))
        ->patch(route('funds.update'), sharePayload([
            'operating' => 20,
            'owner_pay' => 50,
        ]))
        ->assertRedirect(route('funds.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect((float) Fund::query()->where('slug', 'operating')->value('percentage'))->toBe(20.0)
        ->and((float) Fund::query()->where('slug', 'owner_pay')->value('percentage'))->toBe(50.0);
});

it('refuses a split that does not add up to 100', function () {
    $this->from(route('funds.index'))
        ->patch(route('funds.update'), sharePayload(['operating' => 45]))
        ->assertSessionHasErrors('funds');

    expect((float) Fund::query()->where('slug', 'operating')->value('percentage'))->toBe(30.0);
});

it('counts only the active funds towards the 100', function () {
    $funds = sharePayload();

    // Switch off emergency (10%) and give its share to tax.
    foreach ($funds['funds'] as $i => $row) {
        $slug = Fund::query()->whereKey($row['id'])->value('slug');

        if ($slug === 'emergency') {
            $funds['funds'][$i]['is_active'] = false;
        }

        if ($slug === 'tax') {
            $funds['funds'][$i]['percentage'] = 20;
        }
    }

    $this->from(route('funds.index'))
        ->patch(route('funds.update'), $funds)
        ->assertRedirect(route('funds.index'));

    expect(Fund::query()->where('slug', 'emergency')->value('is_active'))->toBeFalsy()
        ->and((float) Fund::query()->where('slug', 'tax')->value('percentage'))->toBe(20.0);
});

it('rejects a percentage over 100', function () {
    $this->from(route('funds.index'))
        ->patch(route('funds.update'), sharePayload(['operating' => 150]))
        ->assertSessionHasErrors('funds.0.percentage');
});

it('moves money between two funds without changing the total', function () {
    // Give the funds something to move.
    FundMovement::query()->create([
        'fund_id' => Fund::query()->where('slug', 'operating')->value('id'),
        'type' => FundMovementType::Adjust,
        'amount' => 100_000,
        'date' => '2026-10-30',
    ]);

    $this->from(route('funds.index'))->post(route('funds.transfer'), [
        'from_fund_id' => Fund::query()->where('slug', 'operating')->value('id'),
        'to_fund_id' => Fund::query()->where('slug', 'tax')->value('id'),
        'amount' => '250.50',
        'date' => '2026-10-30',
        'note' => 'Simpan untuk cukai',
    ])->assertRedirect(route('funds.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $balances = app(FundBalanceService::class);

    expect($balances->for(Fund::query()->where('slug', 'operating')->firstOrFail()))->toBe(74_950)
        ->and($balances->for(Fund::query()->where('slug', 'tax')->firstOrFail()))->toBe(25_050)
        ->and($balances->total())->toBe(100_000);
});

it('creates a matching pair of movements for a transfer', function () {
    $this->from(route('funds.index'))->post(route('funds.transfer'), [
        'from_fund_id' => Fund::query()->where('slug', 'operating')->value('id'),
        'to_fund_id' => Fund::query()->where('slug', 'tax')->value('id'),
        'amount' => '100',
        'date' => '2026-10-30',
    ]);

    $movements = FundMovement::query()->orderBy('id')->get();

    expect($movements)->toHaveCount(2)
        ->and($movements[0]->type)->toBe(FundMovementType::TransferOut)
        ->and($movements[0]->amount)->toBe(-10_000)
        ->and($movements[1]->type)->toBe(FundMovementType::TransferIn)
        ->and($movements[1]->amount)->toBe(10_000)
        // No account transaction: no real money moved.
        ->and($movements->pluck('transaction_id')->filter()->all())->toBe([]);
});

it('refuses a transfer to the same fund', function () {
    $operating = Fund::query()->where('slug', 'operating')->value('id');

    $this->from(route('funds.index'))->post(route('funds.transfer'), [
        'from_fund_id' => $operating,
        'to_fund_id' => $operating,
        'amount' => '100',
        'date' => '2026-10-30',
    ])->assertSessionHasErrors('to_fund_id');

    expect(FundMovement::query()->count())->toBe(0);
});

it('keeps guests out', function () {
    auth()->logout();

    $this->get(route('funds.index'))->assertRedirect(route('login'));
});
