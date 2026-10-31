<?php

namespace App\Notifications\Concerns;

/**
 * A database notification stores a translation key and its parameters, never
 * a finished sentence (PLAN.md phase 4).
 *
 * The reason is that a notification outlives the moment it was created. One
 * written while the app was in Malay is often read after the owner has
 * switched to English, and a stored sentence would stay in the language it
 * happened to be written in. Keeping the key means the bell menu renders it
 * in whatever language is active when it is read — and re-renders it on the
 * spot when the language is switched.
 */
trait RendersInReadersLanguage
{
    /**
     * Amounts go in as `amount_sen` and dates as ISO strings: a formatted
     * "RM 49.90" or "20 Okt 2026" would be stuck in the language it was
     * written in, exactly like a stored sentence would be. The page formats
     * both with the reader's locale.
     *
     * @param  array<string, string|int>  $params
     * @return array<string, mixed>
     */
    protected function payload(
        string $key,
        array $params,
        ?string $url = null,
        string $tone = 'info',
    ): array {
        return [
            'key' => $key,
            'params' => $params,
            'url' => $url,
            'tone' => $tone,
        ];
    }
}
