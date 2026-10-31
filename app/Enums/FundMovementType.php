<?php

namespace App\Enums;

enum FundMovementType: string
{
    /** An income split across the funds by percentage. */
    case Allocate = 'allocate';
    /** An expense or owner draw taken out of a fund. */
    case Spend = 'spend';
    /** A manual correction. */
    case Adjust = 'adjust';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function label(): string
    {
        return match ($this) {
            self::Allocate => __('fund.movement.allocate'),
            self::Spend => __('fund.movement.spend'),
            self::Adjust => __('fund.movement.adjust'),
            self::TransferIn => __('fund.movement.transfer_in'),
            self::TransferOut => __('fund.movement.transfer_out'),
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
}
