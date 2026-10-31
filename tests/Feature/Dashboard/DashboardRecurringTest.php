<?php

use App\Enums\RecurringInterval;
use App\Models\Client;
use App\Models\RecurringItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\FundSeeder;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-15 09:00:00');

    $this->seed(FundSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->actingAs(User::factory()->create());
    $this->client = Client::factory()->create();
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('adds up what the subscriptions are expected to do next month', function () {
    RecurringItem::factory()->clientBilling($this->client)
        ->every(RecurringInterval::Monthly)->dueOn('2026-10-20')
        ->amount(1_500_00)->create();
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)->dueOn('2026-10-25')
        ->amount(49_90)->create();
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Yearly)->dueOn('2026-11-01')
        ->amount(360_00)->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('upcomingRecurring.days', 30)
            ->where('upcomingRecurring.income', 1_500_00)
            // 49.90 + 360.00
            ->where('upcomingRecurring.expense', 409_90)
            ->where('upcomingRecurring.net', 1_500_00 - 409_90)
            ->has('upcomingRecurringItems', 3),
        );
});

it('counts a monthly charge twice when it falls twice in the window', function () {
    // Today is 15 October, so the window runs to 14 November. A charge on
    // the 14th falls on both ends of it, and both turns are real money.
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)->dueOn('2026-10-14')
        ->amount(100_00)->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('upcomingRecurring.expense', 200_00),
        );

    // One day later and the second turn is outside the window.
    RecurringItem::query()->delete();
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Monthly)->dueOn('2026-10-16')
        ->amount(100_00)->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('upcomingRecurring.expense', 100_00),
        );
});

it('leaves out charges past the window and items switched off', function () {
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Yearly)->dueOn('2026-12-31')
        ->amount(999_00)->create();
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Yearly)->dueOn('2026-10-20')
        ->amount(500_00)->inactive()->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('upcomingRecurring.expense', 0)
            ->has('upcomingRecurringItems', 0),
        );
});

it('counts a charge whose date has already gone by', function () {
    // Overdue is still owed, so it belongs in what is coming.
    RecurringItem::factory()->ownCost()
        ->every(RecurringInterval::Yearly)->dueOn('2026-09-30')
        ->amount(240_00)->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('upcomingRecurring.expense', 240_00),
        );
});

it('lists the charges soonest first', function () {
    foreach (['2026-11-10', '2026-10-18', '2026-10-30'] as $date) {
        RecurringItem::factory()->ownCost()
            ->every(RecurringInterval::Yearly)->dueOn($date)->create();
    }

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('upcomingRecurringItems.0.next_date', '2026-10-18')
            ->where('upcomingRecurringItems.1.next_date', '2026-10-30')
            ->where('upcomingRecurringItems.2.next_date', '2026-11-10'),
        );
});

it('reports nothing when there are no recurring items', function () {
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('upcomingRecurring.income', 0)
            ->where('upcomingRecurring.expense', 0)
            ->where('upcomingRecurring.net', 0),
        );
});

it('keeps the dashboard query count flat as recurring items pile up', function () {
    $this->get(route('dashboard'))->assertOk();

    $measure = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    RecurringItem::factory()->ownCost()->count(3)->create();
    $small = $measure();

    RecurringItem::factory()->ownCost()->count(40)->create();

    // The window maths runs in PHP over a small set, so the query count must
    // not move with the number of items.
    expect($measure())->toBe($small);
});
