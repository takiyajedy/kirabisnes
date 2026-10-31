<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Read a setting value, falling back to $default when it is not set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            self::cacheKey($key),
            fn () => self::query()->where('key', $key)->value('value'),
        ) ?? $default;
    }

    /**
     * Write a setting value.
     */
    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::cacheKey($key));
    }

    /**
     * Read many settings at once.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function many(array $defaults): array
    {
        $values = [];

        foreach ($defaults as $key => $default) {
            $values[$key] = self::get($key, $default);
        }

        return $values;
    }

    /**
     * Write many settings at once.
     *
     * @param  array<string, mixed>  $values
     */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::put($key, $value);
        }
    }

    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }
}
