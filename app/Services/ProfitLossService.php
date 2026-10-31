<?php

namespace App\Services;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Period;

/**
 * Profit and loss for a window, broken down by category (PLAN.md phase 5).
 *
 * The totals are the same ones the dashboard shows, and for the same reason:
 * income and expenses are trading, an owner draw and a capital injection are
 * the owner's own money moving and belong nowhere near a profit figure.
 */
class ProfitLossService
{
    /**
     * @return array{
     *     income: int, expense: int, net: int,
     *     income_rows: array<int, array{name: string, amount: int, share: float}>,
     *     expense_rows: array<int, array{name: string, amount: int, share: float}>,
     *     months: array<int, array{month: string, income: int, expense: int, net: int}>,
     * }
     */
    public function report(Period $period): array
    {
        $income = $this->byCategory($period, CategoryType::Income);
        $expense = $this->byCategory($period, CategoryType::Expense);

        $incomeTotal = array_sum(array_column($income, 'amount'));
        $expenseTotal = array_sum(array_column($expense, 'amount'));

        return [
            'income' => $incomeTotal,
            'expense' => $expenseTotal,
            'net' => $incomeTotal - $expenseTotal,
            'income_rows' => $this->withShares($income, $incomeTotal),
            'expense_rows' => $this->withShares($expense, $expenseTotal),
            'months' => $this->byMonth($period),
        ];
    }

    /**
     * One row per category that had any money in the window, largest first.
     *
     * @return array<int, array{name: string, amount: int}>
     */
    public function byCategory(Period $period, CategoryType $type): array
    {
        $rows = Transaction::query()
            ->where('type', $type === CategoryType::Income ? 'income' : 'expense')
            ->between($period->fromDate(), $period->toDate())
            ->toBase()
            ->selectRaw('category_id, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('category_id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = Category::query()
            ->whereIn('id', $rows->pluck('category_id')->filter()->all())
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name]);

        return $rows
            ->map(fn ($row) => [
                'name' => $row->category_id === null
                    ? __('report.uncategorised')
                    : ($names[$row->category_id] ?? __('report.uncategorised')),
                'amount' => (int) $row->total,
            ])
            ->filter(fn (array $row) => $row['amount'] !== 0)
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Month by month across the window, so a year reads as twelve rows.
     *
     * @return array<int, array{month: string, income: int, expense: int, net: int}>
     */
    public function byMonth(Period $period): array
    {
        // SUBSTR over the date gives 'YYYY-MM' on both MySQL and SQLite.
        $rows = Transaction::query()
            ->between($period->fromDate(), $period->toDate())
            ->toBase()
            ->selectRaw('SUBSTR(transactions.date, 1, 7) AS ym')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                 COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense",
            )
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $months = [];
        $month = $period->from->startOfMonth();
        $last = $period->to->startOfMonth();

        while ($month->lessThanOrEqualTo($last)) {
            $row = $rows->get($month->format('Y-m'));
            $income = (int) ($row->income ?? 0);
            $expense = (int) ($row->expense ?? 0);

            $months[] = [
                'month' => $month->toDateString(),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ];

            $month = $month->addMonth();
        }

        return $months;
    }

    /**
     * Each row's share of its side's total, for the percentage column.
     *
     * @param  array<int, array{name: string, amount: int}>  $rows
     * @return array<int, array{name: string, amount: int, share: float}>
     */
    private function withShares(array $rows, int $total): array
    {
        return array_map(
            fn (array $row) => [
                ...$row,
                'share' => $total === 0
                    ? 0.0
                    : round(($row['amount'] / $total) * 100, 1),
            ],
            $rows,
        );
    }
}
