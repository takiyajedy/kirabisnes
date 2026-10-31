<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Fund;
use App\Models\Invoice;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Every figure on the dashboard, gathered in aggregate queries so the page
 * stays fast as the transaction table grows (PLAN.md phase 2).
 *
 * "Money in" counts income and "money out" counts expenses only. An owner
 * draw and a capital injection move the owner's own money in or out of the
 * business, so counting them would make the net figure stop being profit.
 */
class DashboardService
{
    /** How many slices the expense donut shows before grouping the rest. */
    private const DONUT_SLICES = 5;

    public function __construct(
        private AccountBalanceService $accountBalances,
        private FundBalanceService $fundBalances,
    ) {}

    /**
     * Money in, money out and the difference, for one window.
     *
     * @return array{income: int, expense: int, net: int}
     */
    public function totals(Period $period): array
    {
        // toBase() keeps the soft-delete scope but hands back a plain row,
        // which is what an aggregate select is: not a Transaction.
        $row = Transaction::query()
            ->between($period->fromDate(), $period->toDate())
            ->toBase()
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                 COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense",
            )
            ->first();

        $income = (int) ($row->income ?? 0);
        $expense = (int) ($row->expense ?? 0);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    /**
     * The figures for the stat cards: the window's totals, and how each one
     * compares with the previous window.
     *
     * A change against nothing has no percentage, so those come back null and
     * the card simply leaves the comparison line out.
     *
     * @return array{
     *     income: int, expense: int, net: int,
     *     income_change: ?float, expense_change: ?float, net_change: ?float,
     * }
     */
    public function summary(Period $period): array
    {
        $current = $this->totals($period);
        $previous = $this->totals($period->previous());

        return [
            ...$current,
            'income_change' => self::percentChange($current['income'], $previous['income']),
            'expense_change' => self::percentChange($current['expense'], $previous['expense']),
            'net_change' => self::percentChange($current['net'], $previous['net']),
        ];
    }

    /**
     * Every fund with its balance attached, in allocation order.
     *
     * @return Collection<int, Fund>
     */
    public function funds(): Collection
    {
        $balances = $this->fundBalances->all();

        return Fund::query()->ordered()->get()
            ->each(fn (Fund $fund) => $fund->balance = $balances[$fund->id] ?? 0);
    }

    /**
     * The money on hand across the active accounts.
     */
    public function totalBalance(): int
    {
        return $this->accountBalances->totalActive();
    }

    /**
     * Money in against money out, one bucket per month.
     *
     * Months with no transactions still appear, so the bars stay evenly
     * spaced. The dates come back as ISO strings and the month names are
     * formatted in the browser, which keeps them in the reader's language
     * without another round trip.
     *
     * @return array<int, array{month: string, income: int, expense: int}>
     */
    public function monthlySeries(CarbonImmutable $endsOn, int $months = 12): array
    {
        $last = $endsOn->startOfMonth();
        $first = $last->subMonths($months - 1);

        // SUBSTR over the date gives 'YYYY-MM' on both MySQL and SQLite.
        $rows = Transaction::query()
            ->between($first->toDateString(), $last->endOfMonth()->toDateString())
            ->toBase()
            ->selectRaw('SUBSTR(transactions.date, 1, 7) AS ym')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) AS income,
                 COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS expense",
            )
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $series = [];

        for ($month = $first; $month->lessThanOrEqualTo($last); $month = $month->addMonth()) {
            $row = $rows->get($month->format('Y-m'));

            $series[] = [
                'month' => $month->toDateString(),
                'income' => (int) ($row->income ?? 0),
                'expense' => (int) ($row->expense ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Expenses grouped by category for the window, largest first.
     *
     * Only the few biggest categories get their own slice; the rest are added
     * together, because a donut with a dozen slivers cannot be read. The
     * colours are the chart tokens, assigned by position, so the slices stay
     * legible in both light and dark mode.
     *
     * @return array<int, array{name: string, amount: int, slot: int}>
     */
    public function expensesByCategory(Period $period): array
    {
        $rows = Transaction::query()
            ->where('type', 'expense')
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
            ->mapWithKeys(fn ($category) => [$category->id => $category->name]);

        $slices = $rows
            ->map(fn ($row) => [
                'name' => $row->category_id === null
                    ? __('dashboard.uncategorised')
                    : ($names[$row->category_id] ?? __('dashboard.uncategorised')),
                'amount' => (int) $row->total,
            ])
            ->filter(fn (array $slice) => $slice['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        $top = $slices->take(self::DONUT_SLICES);
        $rest = $slices->skip(self::DONUT_SLICES);

        $result = $top
            ->values()
            ->map(fn (array $slice, int $index) => [...$slice, 'slot' => $index])
            ->all();

        if ($rest->isNotEmpty()) {
            $result[] = [
                'name' => __('dashboard.other_categories'),
                'amount' => (int) $rest->sum('amount'),
                // Slot -1 asks the chart for the neutral colour.
                'slot' => -1,
            ];
        }

        return $result;
    }

    /**
     * What clients still owe, across every unpaid invoice.
     *
     * Not scoped to the chosen window: money owed is owed whatever period
     * the rest of the dashboard is showing (PLAN.md phase 3).
     *
     * @return array{amount: int, count: int, overdue_amount: int, overdue_count: int}
     */
    public function outstandingInvoices(): array
    {
        $row = Invoice::query()
            ->outstanding()
            ->toBase()
            ->selectRaw('COUNT(*) AS invoice_count')
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) AS owed')
            ->selectRaw(
                "COUNT(CASE WHEN status = 'overdue' THEN 1 END) AS overdue_count",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status = 'overdue' THEN total - paid_amount ELSE 0 END), 0)
                 AS overdue_owed",
            )
            ->first();

        return [
            'amount' => (int) ($row->owed ?? 0),
            'count' => (int) ($row->invoice_count ?? 0),
            'overdue_amount' => (int) ($row->overdue_owed ?? 0),
            'overdue_count' => (int) ($row->overdue_count ?? 0),
        ];
    }

    /**
     * The unpaid invoices closest to their due date, the late ones first.
     *
     * @return Collection<int, Invoice>
     */
    public function invoicesDueSoon(int $limit = 5): Collection
    {
        return Invoice::query()
            ->with('client')
            ->outstanding()
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * What the recurring items are expected to bring in and take out over
     * the next stretch of days (PLAN.md phase 4).
     *
     * A monthly charge can fall twice inside a thirty day window, and both
     * turns are real money, so each item's dates are walked rather than
     * counted once.
     *
     * @return array{income: int, expense: int, net: int, days: int, until: string}
     */
    public function upcomingRecurring(int $days = 30): array
    {
        $today = CarbonImmutable::today();
        $until = $today->addDays($days);

        $income = 0;
        $expense = 0;

        $items = RecurringItem::query()->active()->get();

        foreach ($items as $item) {
            $turns = count(RecurringScheduleService::datesUntil(
                $item->next_date,
                $until,
                $item->interval,
                $item->anchor_day,
            ));

            $total = $item->amount * $turns;

            if ($item->kind->isIncome()) {
                $income += $total;
            } else {
                $expense += $total;
            }
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $income - $expense,
            'days' => $days,
            'until' => $until->toDateString(),
        ];
    }

    /**
     * The recurring charges falling inside the window, soonest first.
     *
     * @return Collection<int, RecurringItem>
     */
    public function upcomingRecurringItems(int $days = 30, int $limit = 5): Collection
    {
        $until = CarbonImmutable::today()->addDays($days);

        return RecurringItem::query()
            ->with(['client', 'category'])
            ->active()
            ->whereDate('next_date', '<=', $until->toDateString())
            ->ordered()
            ->limit($limit)
            ->get();
    }

    /**
     * The latest transactions inside the window.
     *
     * @return Collection<int, Transaction>
     */
    public function recentTransactions(Period $period, int $limit = 5): Collection
    {
        return Transaction::query()
            ->with(['account', 'toAccount', 'category', 'fund'])
            ->between($period->fromDate(), $period->toDate())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * The change from one figure to another, as a percentage to one decimal.
     */
    private static function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
