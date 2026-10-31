<?php

namespace App\Services;

use App\Support\DocumentDate;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfWrapper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a report into a file (PLAN.md phase 5).
 *
 * The language is chosen at export time, not taken from the app: a report is
 * often sent to an accountant who reads the other one. Everything inside —
 * the CSV headers, the PDF labels, the dates and the amounts — follows that
 * one choice, which is why the whole build runs inside a locale switch.
 */
class ReportExportService
{
    /**
     * A CSV the owner can open in a spreadsheet.
     *
     * @param  array<int, array<int, string|int|float|null>>  $rows  The first
     *                                                               row is the header.
     */
    public function csv(string $fileName, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Excel needs the byte order mark to read UTF-8, and Malay
            // category names have accented characters in them.
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build a report's contents inside the chosen language.
     *
     * @template T
     *
     * @param  callable(): T  $build
     * @return T
     */
    public function inLocale(string $locale, callable $build): mixed
    {
        $previous = App::getLocale();

        App::setLocale($locale);

        try {
            return $build();
        } finally {
            // Whatever happens, the owner's own language comes back.
            App::setLocale($previous);
        }
    }

    /**
     * A printable report. Always light, like the invoices (PLAN.md 3.6).
     *
     * @param  array<string, mixed>  $data
     */
    public function pdf(string $view, array $data, string $locale): PdfWrapper
    {
        return $this->inLocale($locale, fn (): PdfWrapper => Pdf::loadView($view, [
            ...$data,
            'locale' => $locale,
            'money' => fn (int $sen): string => Money::format($sen, $locale),
            'date' => fn (string $date): string => DocumentDate::long(
                CarbonImmutable::parse($date),
                $locale,
            ),
        ])->setPaper('a4'));
    }

    /**
     * A file name that says what it holds and when it was taken.
     */
    public function fileName(string $report, string $window, string $extension): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $report.'-'.$window) ?? $report;

        return trim(strtolower($slug), '-').'.'.$extension;
    }

    /**
     * Money as a plain number for a spreadsheet: 1250.00, never "RM 1,250.00".
     *
     * A formatted string would arrive in the cell as text and refuse to add
     * up, which is the one thing a CSV is for.
     */
    public static function amount(int $sen): string
    {
        return number_format($sen / 100, 2, '.', '');
    }
}
