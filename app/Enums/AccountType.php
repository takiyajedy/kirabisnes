<?php

namespace App\Enums;

enum AccountType: string
{
    case Bank = 'bank';
    case EWallet = 'ewallet';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Bank => __('account.type.bank'),
            self::EWallet => __('account.type.ewallet'),
            self::Cash => __('account.type.cash'),
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
