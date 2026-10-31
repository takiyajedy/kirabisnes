<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Support\BusinessProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hands out the next document number, e.g. INV-2026-0007.
 *
 * The counter is a row per document kind per year. Taking a number locks
 * that row for the rest of the enclosing database transaction, so two
 * requests arriving together queue rather than read the same last number and
 * both write it back (PLAN.md phase 3).
 */
class DocumentNumberService
{
    public function next(DocumentType $type, ?int $year = null): string
    {
        $year ??= CarbonImmutable::today()->year;

        $number = DB::transaction(function () use ($type, $year): int {
            $this->ensureCounterExists($type, $year);

            $sequence = DocumentSequence::query()
                ->where('type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number++;
            $sequence->save();

            return $sequence->last_number;
            // Only the very first document of a year can race on creating the
            // counter, and an ignored insert competing with a row lock can
            // deadlock in InnoDB. Retrying is the documented answer, and the
            // retry finds the row already there.
        }, attempts: 3);

        return $this->format($type, $year, $number);
    }

    /**
     * Shows what the next number will look like, without taking it.
     */
    public function preview(DocumentType $type, ?int $year = null): string
    {
        $year ??= CarbonImmutable::today()->year;

        $last = (int) DocumentSequence::query()
            ->where('type', $type->value)
            ->where('year', $year)
            ->value('last_number');

        return $this->format($type, $year, $last + 1);
    }

    /**
     * lockForUpdate has nothing to lock on a row that does not exist, so the
     * counter is created before it is locked.
     *
     * The read comes first and takes no locks, which matters: once the year's
     * counter exists — the case for all but the first document — this costs
     * one plain select and never touches the row that is about to be locked.
     * Writing unconditionally would have every request take a shared lock on
     * a row another request is holding exclusively, which deadlocks.
     */
    private function ensureCounterExists(DocumentType $type, int $year): void
    {
        $exists = DocumentSequence::query()
            ->where('type', $type->value)
            ->where('year', $year)
            ->exists();

        if ($exists) {
            return;
        }

        // Ignore a clash rather than throw: several requests arriving on a
        // fresh year all find nothing, and only one of them can insert.
        DocumentSequence::query()->insertOrIgnore([
            'type' => $type->value,
            'year' => $year,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function format(DocumentType $type, int $year, int $number): string
    {
        $prefix = BusinessProfile::all()[$type->prefixSetting()];

        return sprintf('%s-%d-%04d', $prefix, $year, $number);
    }
}
