<?php

namespace App\Support;

use App\Models\User;
use Throwable;

/**
 * KiraBisnes is single-user: public registration closes as soon as the first
 * account exists.
 */
final class Registration
{
    public static function isOpen(): bool
    {
        // once() memoises per request, and is flushed between tests.
        return once(function (): bool {
            try {
                return ! User::query()->exists();
            } catch (Throwable) {
                // The database is not ready yet (fresh install) — leave it open.
                return true;
            }
        });
    }
}
