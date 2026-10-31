<?php

use App\Enums\RecurringInterval;
use App\Services\RecurringScheduleService as Schedule;
use Carbon\CarbonImmutable;

/** The next charge date, as a plain string. */
function nextDate(string $from, RecurringInterval $interval, int $anchor): string
{
    return Schedule::next(CarbonImmutable::parse($from), $interval, $anchor)
        ->toDateString();
}

it('adds a month', function () {
    expect(nextDate('2026-03-15', RecurringInterval::Monthly, 15))
        ->toBe('2026-04-15');
});

it('adds a quarter', function () {
    expect(nextDate('2026-01-10', RecurringInterval::Quarterly, 10))
        ->toBe('2026-04-10');
});

it('adds a year', function () {
    expect(nextDate('2026-06-01', RecurringInterval::Yearly, 1))
        ->toBe('2027-06-01');
});

it('clamps 31 January to the end of February', function () {
    expect(nextDate('2026-01-31', RecurringInterval::Monthly, 31))
        ->toBe('2026-02-28');
});

it('clamps to 29 February in a leap year', function () {
    expect(nextDate('2028-01-31', RecurringInterval::Monthly, 31))
        ->toBe('2028-02-29');
});

it('comes back to the anchor day after a clamp', function () {
    // This is what the anchor day is for: reading the day off 28 Feb would
    // leave a 31st item stuck on the 28th for good.
    expect(nextDate('2026-02-28', RecurringInterval::Monthly, 31))
        ->toBe('2026-03-31');
});

it('runs a whole year from the 31st without drifting', function () {
    $dates = [];
    $date = CarbonImmutable::parse('2026-01-31');

    foreach (range(1, 12) as $ignored) {
        $dates[] = $date->toDateString();
        $date = Schedule::next($date, RecurringInterval::Monthly, 31);
    }

    expect($dates)->toBe([
        '2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30',
        '2026-05-31', '2026-06-30', '2026-07-31', '2026-08-31',
        '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-31',
    ]);
});

it('handles a 30th anchor through February', function () {
    expect(nextDate('2026-01-30', RecurringInterval::Monthly, 30))
        ->toBe('2026-02-28')
        ->and(nextDate('2026-02-28', RecurringInterval::Monthly, 30))
        ->toBe('2026-03-30');
});

it('keeps a quarterly charge on its anchor through February', function () {
    expect(nextDate('2025-11-30', RecurringInterval::Quarterly, 30))
        ->toBe('2026-02-28')
        ->and(nextDate('2026-02-28', RecurringInterval::Quarterly, 30))
        ->toBe('2026-05-30');
});

it('moves 29 February to 28 February in a common year', function () {
    expect(nextDate('2028-02-29', RecurringInterval::Yearly, 29))
        ->toBe('2029-02-28')
        // And back to the 29th when a leap year comes round again.
        ->and(nextDate('2031-02-28', RecurringInterval::Yearly, 29))
        ->toBe('2032-02-29');
});

it('refuses a nonsense anchor day rather than throwing', function () {
    expect(nextDate('2026-03-15', RecurringInterval::Monthly, 0))
        ->toBe('2026-04-01')
        ->and(nextDate('2026-03-15', RecurringInterval::Monthly, 99))
        ->toBe('2026-04-30');
});

it('lists every charge date inside a window', function () {
    // A monthly charge due on the 5th falls twice in the next 31 days.
    $dates = Schedule::datesUntil(
        CarbonImmutable::parse('2026-10-05'),
        CarbonImmutable::parse('2026-11-05'),
        RecurringInterval::Monthly,
        5,
    );

    expect(array_map(fn ($d) => $d->toDateString(), $dates))
        ->toBe(['2026-10-05', '2026-11-05']);

    // One day earlier and the second turn is outside the window.
    $narrower = Schedule::datesUntil(
        CarbonImmutable::parse('2026-10-05'),
        CarbonImmutable::parse('2026-11-04'),
        RecurringInterval::Monthly,
        5,
    );

    expect($narrower)->toHaveCount(1);
});

it('lists nothing when the next charge is past the window', function () {
    $dates = Schedule::datesUntil(
        CarbonImmutable::parse('2026-12-01'),
        CarbonImmutable::parse('2026-11-04'),
        RecurringInterval::Monthly,
        1,
    );

    expect($dates)->toBe([]);
});

it('stops listing at the catch up cap', function () {
    $dates = Schedule::datesUntil(
        CarbonImmutable::parse('2020-01-01'),
        CarbonImmutable::parse('2030-01-01'),
        RecurringInterval::Monthly,
        1,
    );

    expect($dates)->toHaveCount(Schedule::MAX_CATCH_UP);
});

it('takes the anchor day from a date', function () {
    expect(Schedule::anchorFor('2026-01-31'))->toBe(31)
        ->and(Schedule::anchorFor(CarbonImmutable::parse('2026-02-09')))->toBe(9);
});
