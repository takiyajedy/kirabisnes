<?php

namespace App\Services;

use App\Enums\RecurringInterval;
use App\Models\RecurringItem;
use Carbon\CarbonImmutable;

/**
 * Works out when a recurring charge falls next (PLAN.md phase 4).
 *
 * The interesting case is a charge anchored on a day that not every month
 * has. A monthly item starting on 31 January should run
 *
 *   31 Jan -> 28 Feb -> 31 Mar -> 30 Apr -> 31 May
 *
 * and not get stuck on the 28th once February has clamped it. That is why
 * the anchor day is kept on the row rather than read back off `next_date`:
 * the clamp is applied fresh each time, from the day the owner chose.
 */
class RecurringScheduleService
{
    /**
     * How many periods a single run will catch up on.
     *
     * An app left closed for a while really does owe an invoice per month
     * that went by, so the catch-up is deliberate; the cap is only there so
     * a date typed in by mistake cannot spin.
     */
    public const MAX_CATCH_UP = 24;

    /**
     * The date one interval after the given one, clamped to the anchor day.
     */
    public static function next(
        CarbonImmutable $from,
        RecurringInterval $interval,
        int $anchorDay,
    ): CarbonImmutable {
        // addMonthsNoOverflow keeps 31 Jan + 1 month inside February instead
        // of spilling into March.
        $target = $from->addMonthsNoOverflow($interval->months());

        return $target->day(min(max(1, $anchorDay), $target->daysInMonth));
    }

    /**
     * Move an item on to its next charge date.
     */
    public function advance(RecurringItem $item): RecurringItem
    {
        $item->next_date = self::next(
            $item->next_date,
            $item->interval,
            $item->anchor_day,
        );

        $item->save();

        return $item;
    }

    /**
     * The charge dates from this item's next one up to a cut-off.
     *
     * Used by the "next 30 days" figures on the dashboard: a monthly item
     * can fall twice inside a window, and both turns are money.
     *
     * @return array<int, CarbonImmutable>
     */
    public static function datesUntil(
        CarbonImmutable $from,
        CarbonImmutable $until,
        RecurringInterval $interval,
        int $anchorDay,
    ): array {
        $dates = [];
        $date = $from;

        for ($i = 0; $i < self::MAX_CATCH_UP; $i++) {
            if ($date->greaterThan($until)) {
                break;
            }

            $dates[] = $date;
            $date = self::next($date, $interval, $anchorDay);
        }

        return $dates;
    }

    /**
     * The anchor day a date implies, for a new or edited item.
     */
    public static function anchorFor(CarbonImmutable|string $date): int
    {
        $date = $date instanceof CarbonImmutable
            ? $date
            : CarbonImmutable::parse($date);

        return $date->day;
    }
}
