<?php

namespace App\Actions\Time;

use App\Models\TimeEntry;
use Carbon\CarbonImmutable;

/**
 * Stops a running timer and writes down how long it ran.
 *
 * The minutes are stored rather than derived from the two timestamps, for
 * the same reason an invoice line keeps its amount: a rounding rule that
 * changes later must not quietly rewrite work already logged.
 */
class StopTimer
{
    public function handle(TimeEntry $entry, ?CarbonImmutable $at = null): TimeEntry
    {
        if (! $entry->isRunning()) {
            return $entry;
        }

        $at ??= CarbonImmutable::now();

        $entry->ended_at = $at;
        // A timer stopped within thirty seconds still counts as a minute:
        // zero would read as if nothing had happened.
        $entry->minutes = max(1, (int) round($entry->started_at->diffInSeconds($at) / 60));
        $entry->save();

        return $entry;
    }
}
