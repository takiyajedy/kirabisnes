<?php

namespace App\Services;

use App\Enums\RecurringKind;
use App\Models\Invoice;
use App\Models\RecurringItem;
use Carbon\CarbonImmutable;

/**
 * Where the bank balance is heading over the next 30, 60 and 90 days
 * (PLAN.md phase 5).
 *
 *   today's balance
 *     + unpaid invoices, on their due date
 *     + recurring client charges, on their charge dates
 *     − recurring own costs, on their charge dates
 *
 * It is a projection from what is already known, not a prediction: work not
 * yet quoted and costs not yet set up are not in it. An invoice already past
 * its due date is counted at the start of the window, because that money is
 * owed now and the alternative — leaving it out — would read as if it had
 * been written off.
 */
class CashFlowForecastService
{
    /** The horizons the report shows. */
    public const HORIZONS = [30, 60, 90];

    public function __construct(private AccountBalanceService $balances) {}

    /**
     * @return array{
     *     opening: int,
     *     points: array<int, array{date: string, balance: int, in: int, out: int}>,
     *     horizons: array<int, array{days: int, date: string, balance: int, in: int, out: int}>,
     *     expected_in: int, expected_out: int,
     * }
     */
    public function report(int $days = 90): array
    {
        $today = CarbonImmutable::today();
        $until = $today->addDays($days);
        $opening = $this->balances->totalActive();

        // Everything that moves, bucketed by the day it lands on.
        $daily = $this->invoiceInflows($today, $until);

        foreach ($this->recurringFlows($today, $until) as $date => $flow) {
            $daily[$date]['in'] = ($daily[$date]['in'] ?? 0) + $flow['in'];
            $daily[$date]['out'] = ($daily[$date]['out'] ?? 0) + $flow['out'];
        }

        $points = [];
        $horizons = [];
        $balance = $opening;
        $runningIn = 0;
        $runningOut = 0;

        for ($i = 0; $i <= $days; $i++) {
            $date = $today->addDays($i);
            $key = $date->toDateString();

            $in = $daily[$key]['in'] ?? 0;
            $out = $daily[$key]['out'] ?? 0;

            $balance += $in - $out;
            $runningIn += $in;
            $runningOut += $out;

            $points[] = [
                'date' => $key,
                'balance' => $balance,
                'in' => $in,
                'out' => $out,
            ];

            if (in_array($i, self::HORIZONS, true)) {
                $horizons[] = [
                    'days' => $i,
                    'date' => $key,
                    'balance' => $balance,
                    'in' => $runningIn,
                    'out' => $runningOut,
                ];
            }
        }

        return [
            'opening' => $opening,
            'points' => $points,
            'horizons' => $horizons,
            'expected_in' => $runningIn,
            'expected_out' => $runningOut,
        ];
    }

    /**
     * What unpaid invoices are expected to bring in, on their due dates.
     *
     * @return array<string, array{in: int, out: int}>
     */
    private function invoiceInflows(CarbonImmutable $today, CarbonImmutable $until): array
    {
        $daily = [];

        $invoices = Invoice::query()
            ->outstanding()
            ->whereDate('due_date', '<=', $until->toDateString())
            ->get(['id', 'due_date', 'total', 'paid_amount']);

        foreach ($invoices as $invoice) {
            // Already overdue: the money is owed now, so it lands today
            // rather than on a date that has gone by.
            $date = $invoice->due_date->lessThan($today)
                ? $today
                : $invoice->due_date;

            $key = $date->toDateString();
            $daily[$key]['in'] = ($daily[$key]['in'] ?? 0) + $invoice->outstanding();
            $daily[$key]['out'] ??= 0;
        }

        return $daily;
    }

    /**
     * What the subscriptions are expected to do, on their charge dates.
     *
     * @return array<string, array{in: int, out: int}>
     */
    private function recurringFlows(CarbonImmutable $today, CarbonImmutable $until): array
    {
        $daily = [];

        foreach (RecurringItem::query()->active()->get() as $item) {
            $dates = RecurringScheduleService::datesUntil(
                $item->next_date,
                $until,
                $item->interval,
                $item->anchor_day,
            );

            foreach ($dates as $date) {
                // A charge whose date has gone by is still owed, so it lands
                // at the start of the window.
                $key = ($date->lessThan($today) ? $today : $date)->toDateString();

                $daily[$key]['in'] ??= 0;
                $daily[$key]['out'] ??= 0;

                if ($item->kind === RecurringKind::ClientBilling) {
                    $daily[$key]['in'] += $item->amount;
                } else {
                    $daily[$key]['out'] += $item->amount;
                }
            }
        }

        return $daily;
    }
}
