<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => __('project.status.prospect'),
            self::Active => __('project.status.active'),
            self::Completed => __('project.status.completed'),
            self::OnHold => __('project.status.on_hold'),
            self::Cancelled => __('project.status.cancelled'),
        };
    }

    /**
     * The badge tone, matching the StatusBadge component.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Prospect => 'neutral',
            self::Active => 'info',
            self::Completed => 'income',
            self::OnHold => 'warning',
            self::Cancelled => 'expense',
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
