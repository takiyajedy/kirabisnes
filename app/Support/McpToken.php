<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * The single bearer token that lets an AI client reach the MCP server.
 *
 * Only the hash is kept. The token itself is shown once, when it is minted,
 * and cannot be read back — the same bargain as an API key anywhere else.
 *
 * SHA-256 rather than bcrypt: this is 160 bits of randomness, not a password.
 * There is no dictionary to attack, and bcrypt would put ~50ms of key
 * stretching in front of every tool call for no gain.
 */
final class McpToken
{
    private const SETTING = 'mcp_token_hash';

    private const PREFIX = 'kb_';

    /**
     * Mint a new token, replacing any existing one.
     *
     * Returns the plain token. It is never recoverable afterwards.
     */
    public static function mint(): string
    {
        $token = self::PREFIX.Str::random(40);

        Setting::put(self::SETTING, self::hash($token));

        return $token;
    }

    /**
     * Whether this token is the one that was minted.
     */
    public static function matches(string $token): bool
    {
        $stored = Setting::get(self::SETTING);

        if (! is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, self::hash($token));
    }

    public static function exists(): bool
    {
        $stored = Setting::get(self::SETTING);

        return is_string($stored) && $stored !== '';
    }

    public static function revoke(): void
    {
        Setting::put(self::SETTING, null);
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
