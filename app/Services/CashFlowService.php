<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Period;

/**
 * Money in and out of each account over a window (PLAN.md phase 5).
 *
 * This is cash flow, not profit, so it counts every movement — an owner draw
 * and a capital injection leave and enter the bank just like anything else.
 * Transfers between your own accounts appear on both sides, because that is
 * what the bank statement says, and they cancel out in the totals.
 */
class CashFlowService
{
    /**
     * @return array{
     *     rows: array<int, array{
     *         id: int, name: string, type_label: string,
     *         opening: int, in: int, out: int, net: int, closing: int,
     *     }>,
     *     totals: array{opening: int, in: int, out: int, net: int, closing: int},
     * }
     */
    public function report(Period $period): array
    {
        $accounts = Account::query()->ordered()->get();

        $movements = $this->movements($period);
        $before = $this->balancesBefore($period);

        $rows = [];
        $totals = ['opening' => 0, 'in' => 0, 'out' => 0, 'net' => 0, 'closing' => 0];

        foreach ($accounts as $account) {
            $opening = $before[$account->id] ?? $account->opening_balance;
            $in = $movements[$account->id]['in'] ?? 0;
            $out = $movements[$account->id]['out'] ?? 0;

            $rows[] = [
                'id' => $account->id,
                'name' => $account->name,
                'type_label' => $account->type->label(),
                'opening' => $opening,
                'in' => $in,
                'out' => $out,
                'net' => $in - $out,
                'closing' => $opening + $in - $out,
            ];

            $totals['opening'] += $opening;
            $totals['in'] += $in;
            $totals['out'] += $out;
            $totals['net'] += $in - $out;
            $totals['closing'] += $opening + $in - $out;
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * What went in and out of each account inside the window.
     *
     * @return array<int, array{in: int, out: int}>
     */
    private function movements(Period $period): array
    {
        $byAccount = [];

        // Everything recorded against the account itself.
        $own = Transaction::query()
            ->between($period->fromDate(), $period->toDate())
            ->toBase()
            ->selectRaw('account_id')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type IN ('income', 'capital_injection')
                    THEN amount ELSE 0 END), 0) AS money_in",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type IN ('expense', 'owner_draw', 'transfer')
                    THEN amount ELSE 0 END), 0) AS money_out",
            )
            ->groupBy('account_id')
            ->get();

        foreach ($own as $row) {
            $byAccount[(int) $row->account_id] = [
                'in' => (int) $row->money_in,
                'out' => (int) $row->money_out,
            ];
        }

        // The receiving end of a transfer between your own accounts.
        $incoming = Transaction::query()
            ->where('type', 'transfer')
            ->whereNotNull('to_account_id')
            ->between($period->fromDate(), $period->toDate())
            ->toBase()
            ->selectRaw('to_account_id, COALESCE(SUM(amount), 0) AS money_in')
            ->groupBy('to_account_id')
            ->get();

        foreach ($incoming as $row) {
            $id = (int) $row->to_account_id;
            $byAccount[$id]['in'] = ($byAccount[$id]['in'] ?? 0) + (int) $row->money_in;
            $byAccount[$id]['out'] ??= 0;
        }

        return $byAccount;
    }

    /**
     * Each account's balance the day before the window opens.
     *
     * @return array<int, int>
     */
    private function balancesBefore(Period $period): array
    {
        $day = $period->from->subDay()->toDateString();

        $balances = Account::query()->pluck('opening_balance', 'id')
            ->map(fn (int $opening) => $opening)
            ->all();

        $own = Transaction::query()
            ->whereDate('date', '<=', $day)
            ->toBase()
            ->selectRaw('account_id')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type IN ('income', 'capital_injection')
                    THEN amount ELSE 0 END), 0)
                 - COALESCE(SUM(CASE WHEN type IN ('expense', 'owner_draw', 'transfer')
                    THEN amount ELSE 0 END), 0) AS delta",
            )
            ->groupBy('account_id')
            ->get();

        foreach ($own as $row) {
            $id = (int) $row->account_id;
            $balances[$id] = ($balances[$id] ?? 0) + (int) $row->delta;
        }

        $incoming = Transaction::query()
            ->where('type', 'transfer')
            ->whereNotNull('to_account_id')
            ->whereDate('date', '<=', $day)
            ->toBase()
            ->selectRaw('to_account_id, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('to_account_id')
            ->get();

        foreach ($incoming as $row) {
            $id = (int) $row->to_account_id;
            $balances[$id] = ($balances[$id] ?? 0) + (int) $row->total;
        }

        return $balances;
    }
}
