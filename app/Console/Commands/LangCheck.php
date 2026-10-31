<?php

namespace App\Console\Commands;

use App\Enums\Locale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Fails when the translation files drift apart, so a new string can never be
 * shipped in one language only.
 */
class LangCheck extends Command
{
    protected $signature = 'lang:check';

    protected $description = 'Report translation keys that are missing from one of the languages';

    public function handle(): int
    {
        $locales = Locale::values();
        $problems = 0;

        $problems += $this->checkJsonFiles($locales);
        $problems += $this->checkPhpFiles($locales);

        if ($problems > 0) {
            $this->newLine();
            $this->error("{$problems} translation problem(s) found.");

            return self::FAILURE;
        }

        $this->info('All translations are in sync.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function checkJsonFiles(array $locales): int
    {
        $keys = [];
        $problems = 0;

        foreach ($locales as $locale) {
            $path = lang_path("{$locale}.json");

            if (! File::exists($path)) {
                $this->error("Missing translation file: lang/{$locale}.json");

                return 1;
            }

            $decoded = json_decode(File::get($path), true);

            if (! is_array($decoded)) {
                $this->error("lang/{$locale}.json is not valid JSON.");

                return 1;
            }

            $keys[$locale] = array_keys($decoded);

            $empty = array_keys(array_filter(
                $decoded,
                fn ($value) => ! is_string($value) || trim($value) === '',
            ));

            foreach ($empty as $key) {
                $this->line("  <fg=yellow>empty</> lang/{$locale}.json → {$key}");
                $problems++;
            }
        }

        return $problems + $this->reportDifferences($keys, fn (string $locale) => "lang/{$locale}.json");
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function checkPhpFiles(array $locales): int
    {
        $problems = 0;
        $groups = [];

        foreach ($locales as $locale) {
            $directory = lang_path($locale);

            if (! File::isDirectory($directory)) {
                $this->error("Missing translation directory: lang/{$locale}");

                return 1;
            }

            foreach (File::files($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $groups[$file->getFilenameWithoutExtension()][$locale] =
                    $this->flatten(require $file->getRealPath());
            }
        }

        foreach ($groups as $group => $byLocale) {
            $missingLocales = array_diff($locales, array_keys($byLocale));

            foreach ($missingLocales as $locale) {
                $this->line("  <fg=red>missing file</> lang/{$locale}/{$group}.php");
                $problems++;
            }

            $keys = array_map(fn (array $lines) => array_keys($lines), $byLocale);

            $problems += $this->reportDifferences(
                $keys,
                fn (string $locale) => "lang/{$locale}/{$group}.php",
            );
        }

        return $problems;
    }

    /**
     * Report keys present in one locale but absent from another.
     *
     * @param  array<string, array<int, string>>  $keysByLocale
     * @param  callable(string): string  $label
     */
    private function reportDifferences(array $keysByLocale, callable $label): int
    {
        $problems = 0;

        foreach ($keysByLocale as $locale => $keys) {
            foreach ($keysByLocale as $otherLocale => $otherKeys) {
                if ($locale === $otherLocale) {
                    continue;
                }

                foreach (array_diff($keys, $otherKeys) as $key) {
                    $this->line(sprintf(
                        '  <fg=red>missing</> %s → %s <fg=gray>(present in %s)</>',
                        $label($otherLocale),
                        $key,
                        $label($locale),
                    ));

                    $problems++;
                }
            }
        }

        return $problems;
    }

    /**
     * Turn a nested translation array into dot-notation keys.
     *
     * @param  array<string, mixed>  $lines
     * @return array<string, string>
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $flat = [];

        foreach ($lines as $key => $value) {
            $dotted = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat = [...$flat, ...$this->flatten($value, $dotted)];

                continue;
            }

            $flat[$dotted] = (string) $value;
        }

        return $flat;
    }
}
