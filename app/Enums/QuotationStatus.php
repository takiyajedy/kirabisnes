<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('quotation.status.draft'),
            self::Sent => __('quotation.status.sent'),
            self::Accepted => __('quotation.status.accepted'),
            self::Rejected => __('quotation.status.rejected'),
            self::Expired => __('quotation.status.expired'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Accepted => 'income',
            self::Rejected => 'expense',
            self::Expired => 'warning',
        };
    }

    /**
     * A quotation that has already become an invoice must not be edited into
     * something the invoice no longer matches.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::Sent => true,
            self::Accepted, self::Rejected, self::Expired => false,
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
