<?php

namespace App\Support;

use Carbon\CarbonInterface;
use IntlDateFormatter;

/**
 * Dates as they appear on a document sent to a client.
 *
 * A PDF and the e-mail that carries it must agree, and both follow the
 * document's own language rather than the app's — so the formatting lives in
 * one place instead of being written out at each call site.
 *
 * ICU does the formatting, which is what the browser's Intl.DateTimeFormat
 * uses too, so the same date reads the same on screen as it does on paper.
 * Carbon's own Malay locale abbreviates August as "Ogs" where CLDR, and so
 * the rest of the app, says "Ogo".
 */
final class DocumentDate
{
    /**
     * "30 Okt 2026" in Malay, "30 Oct 2026" in English.
     */
    public static function long(CarbonInterface $date, string $locale): string
    {
        return self::icu($date, $locale) ?? $date
            ->settings(['locale' => $locale])
            ->isoFormat('D MMM YYYY');
    }

    /**
     * The ICU tag for a document language. Matches lib/locale.ts.
     */
    public static function tag(string $locale): string
    {
        return $locale === 'en' ? 'en_MY' : 'ms_MY';
    }

    /**
     * Null when the intl extension is missing, so the caller can fall back.
     */
    private static function icu(CarbonInterface $date, string $locale): ?string
    {
        if (! class_exists(IntlDateFormatter::class)) {
            return null;
        }

        $formatter = new IntlDateFormatter(
            self::tag($locale),
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            'd MMM y',
        );

        $formatted = $formatter->format($date->toDateTime());

        return $formatted === false ? null : $formatted;
    }
}
