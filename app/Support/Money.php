<?php

namespace App\Support;

use NumberFormatter;

/**
 * All money in KiraBisnes is stored as an integer number of sen.
 * RM 12.50 => 1250. Never use float/decimal for money.
 */
final class Money
{
    /**
     * Convert a ringgit amount (user input) into sen.
     */
    public static function toSen(string|float|int|null $ringgit): int
    {
        if ($ringgit === null || $ringgit === '') {
            return 0;
        }

        if (is_string($ringgit)) {
            // Strip thousands separators, currency symbols and spaces.
            $ringgit = preg_replace('/[^0-9.\-]/', '', $ringgit) ?? '0';

            if ($ringgit === '' || $ringgit === '-' || $ringgit === '.') {
                return 0;
            }
        }

        return (int) round(((float) $ringgit) * 100);
    }

    /**
     * Convert sen into a ringgit value, for pre-filling form inputs.
     */
    public static function toRinggit(int $sen): string
    {
        return number_format($sen / 100, 2, '.', '');
    }

    /**
     * Format sen as a human readable currency string, e.g. "RM 1,250.00".
     */
    public static function format(int $sen, ?string $locale = null): string
    {
        $locale = match ($locale ?? app()->getLocale()) {
            'en' => 'en_MY',
            default => 'ms_MY',
        };

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($sen / 100, 'MYR');

            if ($formatted !== false) {
                return $formatted;
            }
        }

        return 'RM '.number_format($sen / 100, 2);
    }
}
