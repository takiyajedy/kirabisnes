<?php

use App\Enums\DashboardPeriod;
use App\Support\Period;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // A month with 31 days, inside a year, so the window edges are checkable.
    CarbonImmutable::setTestNow('2026-10-15 09:30:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('defaults to the current month', function () {
    $period = Period::resolve(null);

    expect($period->preset)->toBe(DashboardPeriod::ThisMonth)
        ->and($period->fromDate())->toBe('2026-10-01')
        ->and($period->toDate())->toBe('2026-10-31');
});

it('falls back to the current month when the preset is not one we know', function () {
    // A hand-edited or stale query string must still show a dashboard.
    $period = Period::resolve('last_fortnight');

    expect($period->preset)->toBe(DashboardPeriod::ThisMonth)
        ->and($period->fromDate())->toBe('2026-10-01');
});

it('resolves last month', function () {
    $period = Period::resolve('last_month');

    expect($period->fromDate())->toBe('2026-09-01')
        ->and($period->toDate())->toBe('2026-09-30');
});

it('resolves this year', function () {
    $period = Period::resolve('this_year');

    expect($period->fromDate())->toBe('2026-01-01')
        ->and($period->toDate())->toBe('2026-12-31');
});

it('resolves a custom range', function () {
    $period = Period::resolve('custom', '2026-03-05', '2026-04-20');

    expect($period->preset)->toBe(DashboardPeriod::Custom)
        ->and($period->fromDate())->toBe('2026-03-05')
        ->and($period->toDate())->toBe('2026-04-20');
});

it('swaps a custom range given back to front', function () {
    $period = Period::resolve('custom', '2026-04-20', '2026-03-05');

    expect($period->fromDate())->toBe('2026-03-05')
        ->and($period->toDate())->toBe('2026-04-20');
});

it('compares this month against the whole of last month', function () {
    $previous = Period::resolve('this_month')->previous();

    expect($previous->fromDate())->toBe('2026-09-01')
        ->and($previous->toDate())->toBe('2026-09-30');
});

it('compares last month against the month before it', function () {
    $previous = Period::resolve('last_month')->previous();

    expect($previous->fromDate())->toBe('2026-08-01')
        ->and($previous->toDate())->toBe('2026-08-31');
});

it('compares this year against last year', function () {
    $previous = Period::resolve('this_year')->previous();

    expect($previous->fromDate())->toBe('2025-01-01')
        ->and($previous->toDate())->toBe('2025-12-31');
});

it('compares a custom range against the same length before it', function () {
    // 10 days: the 1st to the 10th inclusive.
    $previous = Period::resolve('custom', '2026-06-01', '2026-06-10')->previous();

    expect($previous->fromDate())->toBe('2026-05-22')
        ->and($previous->toDate())->toBe('2026-05-31');
});

it('never charts past the current month', function () {
    // This year runs to December, but the bars would be empty after October.
    expect(Period::resolve('this_year')->chartEndsOn()->toDateString())
        ->toBe('2026-10-01');
});

it('charts up to the end of a window that is already over', function () {
    expect(Period::resolve('last_month')->chartEndsOn()->toDateString())
        ->toBe('2026-09-01');
});
