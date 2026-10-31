<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('invoice.status.draft'),
            self::Sent => __('invoice.status.sent'),
            self::Partial => __('invoice.status.partial'),
            self::Paid => __('invoice.status.paid'),
            self::Overdue => __('invoice.status.overdue'),
            self::Void => __('invoice.status.void'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Partial => 'warning',
            self::Paid => 'income',
            self::Overdue => 'expense',
            self::Void => 'neutral',
        };
    }

    /**
     * Is this invoice still waiting for money?
     */
    public function isOutstanding(): bool
    {
        return match ($this) {
            self::Sent, self::Partial, self::Overdue => true,
            self::Draft, self::Paid, self::Void => false,
        };
    }

    /**
     * A voided invoice is a record of something that did not happen; a paid
     * one has money recorded against it. Neither may be edited.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Sent, self::Overdue => true,
            self::Partial, self::Paid, self::Void => false,
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
