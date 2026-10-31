<?php

namespace App\Support;

use App\Services\CashFlowForecastService;
use Carbon\CarbonImmutable;

/**
 * Builds the sheet for each report (PLAN.md phase 5).
 *
 * Every method runs inside the export language, so `__()` and the figures it
 * labels come out in the one the owner chose at export time.
 */
final class ReportSheets
{
    /**
     * @param  array<string, mixed>  $report
     */
    public static function profitLoss(array $report, Period $period): ReportSheet
    {
        $sheet = ReportSheet::make(
            __('report.profit_loss'),
            self::window($period),
        )
            ->figure(__('dashboard.money_in'), $report['income'])
            ->figure(__('dashboard.money_out'), $report['expense'])
            ->figure(__('dashboard.net_profit'), $report['net']);

        $sheet->table(__('report.income_by_category'), [
            __('transaction.category'), __('common.amount'), __('report.share'),
        ]);

        foreach ($report['income_rows'] as $row) {
            $sheet->row([$row['name'], ['sen' => $row['amount']], $row['share'].'%']);
        }

        $sheet->row([__('common.total'), ['sen' => $report['income']], '100%'], total: true);

        $sheet->table(__('report.expense_by_category'), [
            __('transaction.category'), __('common.amount'), __('report.share'),
        ]);

        foreach ($report['expense_rows'] as $row) {
            $sheet->row([$row['name'], ['sen' => $row['amount']], $row['share'].'%']);
        }

        $sheet->row([__('common.total'), ['sen' => $report['expense']], '100%'], total: true);

        $sheet->table(__('report.by_month'), [
            __('report.month'),
            __('dashboard.money_in'),
            __('dashboard.money_out'),
            __('dashboard.net_profit'),
        ]);

        foreach ($report['months'] as $month) {
            $sheet->row([
                self::month($month['month']),
                ['sen' => $month['income']],
                ['sen' => $month['expense']],
                ['sen' => $month['net']],
            ]);
        }

        $sheet->row([
            __('common.total'),
            ['sen' => $report['income']],
            ['sen' => $report['expense']],
            ['sen' => $report['net']],
        ], total: true);

        return $sheet->note(__('report.profit_note'));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function cashFlow(array $report, Period $period): ReportSheet
    {
        $totals = $report['totals'];

        $sheet = ReportSheet::make(__('report.cash_flow'), self::window($period))
            ->figure(__('report.opening_balance'), $totals['opening'])
            ->figure(__('report.money_in'), $totals['in'])
            ->figure(__('report.money_out'), $totals['out'])
            ->figure(__('report.closing_balance'), $totals['closing']);

        $sheet->table(__('report.by_account'), [
            __('transaction.account'),
            __('report.opening_balance'),
            __('report.money_in'),
            __('report.money_out'),
            __('report.closing_balance'),
        ]);

        foreach ($report['rows'] as $row) {
            $sheet->row([
                $row['name'],
                ['sen' => $row['opening']],
                ['sen' => $row['in']],
                ['sen' => $row['out']],
                ['sen' => $row['closing']],
            ]);
        }

        $sheet->row([
            __('common.total'),
            ['sen' => $totals['opening']],
            ['sen' => $totals['in']],
            ['sen' => $totals['out']],
            ['sen' => $totals['closing']],
        ], total: true);

        return $sheet->note(__('report.cash_flow_note'));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function tax(array $report): ReportSheet
    {
        $sheet = ReportSheet::make(
            __('report.tax_summary'),
            (string) $report['year'],
        )
            ->figure(__('report.total_income'), $report['income'])
            ->figure(__('report.deductible'), $report['deductible'])
            ->figure(__('report.taxable_estimate'), $report['taxable'])
            ->figure(__('report.tax_fund'), $report['tax_fund']);

        $sheet->table(__('report.deductible_by_category'), [
            __('transaction.category'), __('common.amount'),
        ]);

        foreach ($report['deductible_rows'] as $row) {
            $sheet->row([$row['name'], ['sen' => $row['amount']]]);
        }

        $sheet->row([__('common.total'), ['sen' => $report['deductible']]], total: true);

        $sheet->table(__('report.non_deductible_by_category'), [
            __('transaction.category'), __('common.amount'),
        ]);

        foreach ($report['non_deductible_rows'] as $row) {
            $sheet->row([$row['name'], ['sen' => $row['amount']]]);
        }

        $sheet->row([__('common.total'), ['sen' => $report['non_deductible']]], total: true);

        // The plan asks for this in so many words, and it belongs on the
        // page as much as in the file.
        return $sheet->note(__('report.tax_note'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function projects(array $rows): ReportSheet
    {
        $sheet = ReportSheet::make(
            __('report.project_profit'),
            __('report.all_projects'),
        );

        $sheet->figure(
            __('report.projects_counted'),
            value: (string) count($rows),
        );

        $sheet->table(__('report.project_profit'), [
            __('project.one'),
            __('client.one'),
            __('project.received'),
            __('project.costs'),
            __('report.labour'),
            __('report.hours'),
            __('report.profit'),
            __('report.margin'),
        ]);

        foreach ($rows as $row) {
            $sheet->row([
                $row['name'],
                $row['client_name'],
                ['sen' => $row['received']],
                ['sen' => $row['costs']],
                ['sen' => $row['labour']],
                self::hours($row['minutes']),
                ['sen' => $row['profit']],
                $row['margin'] === null ? '—' : $row['margin'].'%',
            ]);
        }

        return $sheet->note(__('report.project_profit_note'));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function forecast(array $report): ReportSheet
    {
        $sheet = ReportSheet::make(
            __('report.forecast'),
            __('report.forecast_window', [
                'days' => (string) max(CashFlowForecastService::HORIZONS),
            ]),
        )
            ->figure(__('report.balance_today'), $report['opening'])
            ->figure(__('report.expected_in'), $report['expected_in'])
            ->figure(__('report.expected_out'), $report['expected_out']);

        $sheet->table(__('report.forecast'), [
            __('report.horizon'),
            __('common.date'),
            __('report.expected_in'),
            __('report.expected_out'),
            __('report.projected_balance'),
        ]);

        foreach ($report['horizons'] as $horizon) {
            $sheet->row([
                __('report.in_days', ['days' => (string) $horizon['days']]),
                DocumentDate::long(
                    CarbonImmutable::parse($horizon['date']),
                    app()->getLocale(),
                ),
                ['sen' => $horizon['in']],
                ['sen' => $horizon['out']],
                ['sen' => $horizon['balance']],
            ]);
        }

        return $sheet->note(__('report.forecast_note'));
    }

    /**
     * "1 Okt 2026 – 31 Okt 2026", in the export language.
     */
    private static function window(Period $period): string
    {
        $locale = app()->getLocale();

        return DocumentDate::long($period->from, $locale)
            .' – '
            .DocumentDate::long($period->to, $locale);
    }

    /**
     * "Okt 2026", in the export language.
     */
    private static function month(string $date): string
    {
        $month = CarbonImmutable::parse($date);

        return (new \IntlDateFormatter(
            DocumentDate::tag(app()->getLocale()),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            null,
            null,
            'MMM y',
        ))->format($month->toDateTime()) ?: $month->format('M Y');
    }

    /**
     * Minutes as hours, to one decimal: 90 minutes reads as 1.5.
     */
    private static function hours(int $minutes): string
    {
        return number_format($minutes / 60, 1, '.', '');
    }
}
