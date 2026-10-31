<?php

namespace App\Enums;

enum Locale: string
{
    case Malay = 'ms';
    case English = 'en';

    /**
     * The language name, written in its own language.
     */
    public function nativeLabel(): string
    {
        return match ($this) {
            self::Malay => 'Bahasa Melayu',
            self::English => 'English',
        };
    }

    /**
     * Short label for the language switcher.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Malay => 'BM',
            self::English => 'EN',
        };
    }

    /**
     * The ICU tag used for date and number formatting.
     */
    public function tag(): string
    {
        return match ($this) {
            self::Malay => 'ms-MY',
            self::English => 'en-MY',
        };
    }

    /**
     * For the pickers that choose a document or client language.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->nativeLabel(),
            ],
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
