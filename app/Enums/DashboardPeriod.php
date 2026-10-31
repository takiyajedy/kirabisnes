<?php

namespace App\Enums;

/**
 * The windows the dashboard can be looked at through (PLAN.md phase 2).
 */
enum DashboardPeriod: string
{
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisYear = 'this_year';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::ThisMonth => __('dashboard.period.this_month'),
            self::LastMonth => __('dashboard.period.last_month'),
            self::ThisYear => __('dashboard.period.this_year'),
            self::Custom => __('dashboard.period.custom'),
        };
    }

    /**
     * The wording of the "vs ..." line under each stat card.
     */
    public function comparisonLabel(): string
    {
        return match ($this) {
            self::ThisMonth, self::LastMonth => __('stat.vs_last_month'),
            self::ThisYear => __('stat.vs_last_year'),
            self::Custom => __('stat.vs_previous_period'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
