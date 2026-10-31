<?php

namespace App\Enums;

/**
 * The two sides of a recurring item: something a client is billed for, and
 * something the business pays for itself (PLAN.md phase 4).
 */
enum RecurringKind: string
{
    case ClientBilling = 'client_billing';
    case OwnCost = 'own_cost';

    public function label(): string
    {
        return match ($this) {
            self::ClientBilling => __('recurring.kind.client_billing'),
            self::OwnCost => __('recurring.kind.own_cost'),
        };
    }

    /**
     * Does this kind bring money in?
     */
    public function isIncome(): bool
    {
        return $this === self::ClientBilling;
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
