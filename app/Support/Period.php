<?php

namespace App\Support;

use App\Enums\DashboardPeriod;
use Carbon\CarbonImmutable;

/**
 * A date window, plus the window it is compared against.
 *
 * The presets compare against the matching calendar period — this month
 * against last month, this year against last year — which is what the "vs"
 * figures on the dashboard are expected to mean. A custom range has no
 * calendar equivalent, so it compares against the window of the same length
 * ending the day before it starts.
 */
final readonly class Period
{
    public function __construct(
        public DashboardPeriod $preset,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /**
     * Build a period from request input, falling back to the current month.
     */
    public static function resolve(?string $preset, ?string $from = null, ?string $to = null): self
    {
        $case = DashboardPeriod::tryFrom((string) $preset) ?? DashboardPeriod::ThisMonth;
        $today = CarbonImmutable::today();

        if ($case === DashboardPeriod::Custom) {
            $start = self::parse($from) ?? $today->startOfMonth();
            $end = self::parse($to) ?? $today->endOfMonth();

            // A backwards range would silently return nothing; swap it instead.
            return $start->greaterThan($end)
                ? new self($case, $end, $start)
                : new self($case, $start, $end);
        }

        return match ($case) {
            DashboardPeriod::LastMonth => new self(
                $case,
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth(),
            ),
            DashboardPeriod::ThisYear => new self(
                $case,
                $today->startOfYear(),
                $today->endOfYear(),
            ),
            default => new self(
                DashboardPeriod::ThisMonth,
                $today->startOfMonth(),
                $today->endOfMonth(),
            ),
        };
    }

    /**
     * The window the figures are compared against.
     */
    public function previous(): self
    {
        return match ($this->preset) {
            DashboardPeriod::ThisMonth, DashboardPeriod::LastMonth => new self(
                $this->preset,
                $this->from->subMonthNoOverflow()->startOfMonth(),
                $this->from->subMonthNoOverflow()->endOfMonth(),
            ),
            DashboardPeriod::ThisYear => new self(
                $this->preset,
                $this->from->subYear()->startOfYear(),
                $this->from->subYear()->endOfYear(),
            ),
            DashboardPeriod::Custom => $this->precedingWindow(),
        };
    }

    /**
     * The last month the 12-month chart should end on.
     *
     * Months that have not happened yet would draw as a run of empty bars, so
     * the chart never looks past the current month.
     */
    public function chartEndsOn(): CarbonImmutable
    {
        $thisMonth = CarbonImmutable::today()->startOfMonth();
        $periodEnd = $this->to->startOfMonth();

        return $periodEnd->greaterThan($thisMonth) ? $thisMonth : $periodEnd;
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    /**
     * @return array{period: string, from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'period' => $this->preset->value,
            'from' => $this->fromDate(),
            'to' => $this->toDate(),
        ];
    }

    /**
     * The same number of days, ending the day before this window starts.
     */
    private function precedingWindow(): self
    {
        $days = $this->from->diffInDays($this->to);
        $end = $this->from->subDay();

        return new self($this->preset, $end->subDays($days), $end);
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
