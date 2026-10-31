<?php

namespace App\Http\Controllers;

use App\Enums\DashboardPeriod;
use App\Enums\Locale;
use App\Services\CashFlowForecastService;
use App\Services\CashFlowService;
use App\Services\ProfitLossService;
use App\Services\ProjectProfitService;
use App\Services\ReportExportService;
use App\Services\TaxSummaryService;
use App\Support\BusinessProfile;
use App\Support\Period;
use App\Support\ReportSheet;
use App\Support\ReportSheets;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The reports (PLAN.md phase 5).
 *
 * Each one is its own page and its own request, so visiting the profit and
 * loss does not pay for the forecast. They share a tab strip and the period
 * picker, which lives in the query string like the other filters.
 *
 * An export builds the same ReportSheet the page shows, inside the language
 * chosen for the file, so the PDF and the CSV cannot drift from each other
 * or from the screen.
 */
class ReportController extends Controller
{
    public function profitLoss(Request $request, ProfitLossService $service): Response
    {
        $period = $this->period($request);

        return Inertia::render('Reports/ProfitLoss', [
            ...$this->shared($period),
            'report' => $service->report($period),
        ]);
    }

    public function cashFlow(Request $request, CashFlowService $service): Response
    {
        $period = $this->period($request);

        return Inertia::render('Reports/CashFlow', [
            ...$this->shared($period),
            'report' => $service->report($period),
        ]);
    }

    public function tax(Request $request, TaxSummaryService $service): Response
    {
        $years = $service->availableYears();
        $year = $this->year($request, $years);

        return Inertia::render('Reports/Tax', [
            ...$this->shared(null),
            'report' => $service->report($year),
            'year' => $year,
            'years' => $years,
        ]);
    }

    public function projects(ProjectProfitService $service): Response
    {
        return Inertia::render('Reports/Projects', [
            ...$this->shared(null),
            'rows' => $service->report(),
        ]);
    }

    public function forecast(CashFlowForecastService $service): Response
    {
        return Inertia::render('Reports/Forecast', [
            ...$this->shared(null),
            'report' => $service->report(max(CashFlowForecastService::HORIZONS)),
        ]);
    }

    /**
     * Download a report as a spreadsheet or a PDF, in the chosen language.
     */
    public function export(
        Request $request,
        string $report,
        ReportExportService $exports,
    ): StreamedResponse|HttpResponse {
        $format = $request->string('format')->value() === 'pdf' ? 'pdf' : 'csv';
        $locale = $this->exportLocale($request);
        $period = $this->period($request);

        // The sheet is built inside the export language, so every label,
        // date and amount inside it follows that one choice.
        [$sheet, $window] = $exports->inLocale(
            $locale,
            fn (): array => $this->sheetFor($report, $period, $request),
        );

        $fileName = $exports->fileName($report, $window, $format);

        if ($format === 'pdf') {
            return $exports->pdf(
                'pdf.report',
                [
                    ...$sheet->forPdf($locale),
                    'business' => BusinessProfile::all(),
                ],
                $locale,
            )->setPaper('a4')->download($fileName);
        }

        return $exports->csv($fileName, $sheet->forCsv());
    }

    /**
     * @return array{0: ReportSheet, 1: string}
     */
    private function sheetFor(string $report, Period $period, Request $request): array
    {
        return match ($report) {
            'cash-flow' => [
                ReportSheets::cashFlow(app(CashFlowService::class)->report($period), $period),
                $period->fromDate().'-'.$period->toDate(),
            ],
            'tax' => (function () use ($request): array {
                $service = app(TaxSummaryService::class);
                $year = $this->year($request, $service->availableYears());

                return [ReportSheets::tax($service->report($year)), (string) $year];
            })(),
            'projects' => [
                ReportSheets::projects(app(ProjectProfitService::class)->report()),
                CarbonImmutable::today()->toDateString(),
            ],
            'forecast' => [
                ReportSheets::forecast(
                    app(CashFlowForecastService::class)
                        ->report(max(CashFlowForecastService::HORIZONS)),
                ),
                CarbonImmutable::today()->toDateString(),
            ],
            // Anything unrecognised falls back to the profit and loss, which
            // is the report the tabs open on.
            default => [
                ReportSheets::profitLoss(app(ProfitLossService::class)->report($period), $period),
                $period->fromDate().'-'.$period->toDate(),
            ],
        };
    }

    /**
     * The window, resolved the same way the dashboard resolves it: bad input
     * falls back rather than failing, so a bookmarked report still opens.
     */
    private function period(Request $request): Period
    {
        return Period::resolve(
            $request->string('period')->value(),
            $request->string('from')->value(),
            $request->string('to')->value(),
        );
    }

    /**
     * @param  array<int, int>  $years
     */
    private function year(Request $request, array $years): int
    {
        $year = $request->integer('year');

        return in_array($year, $years, true)
            ? $year
            : ($years[0] ?? CarbonImmutable::today()->year);
    }

    /**
     * The language the file is written in. Defaults to the one the owner is
     * reading, but an accountant may want the other.
     */
    private function exportLocale(Request $request): string
    {
        $locale = $request->string('locale')->value();

        return in_array($locale, Locale::values(), true)
            ? $locale
            : app()->getLocale();
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(?Period $period): array
    {
        return [
            'period' => $period?->toArray() ?? Period::resolve('this_year')->toArray(),
            'periodOptions' => DashboardPeriod::options(),
            'exportLocales' => Locale::options(),
        ];
    }
}
