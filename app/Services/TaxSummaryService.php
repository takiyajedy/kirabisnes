<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Fund;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * What a year looks like for tax (PLAN.md phase 5).
 *
 * It is an estimate and the report says so on its face: only an expense
 * marked deductible is counted against income, there is no capital
 * allowance, no personal relief and no rate applied. The point is to have
 * the figures in one place when the form has to be filled in, not to work
 * out what is owed.
 *
 * The `tax` fund balance sits beside the estimate so the owner can see
 * whether what has been set aside is anywhere near it.
 */
class TaxSummaryService
{
    public function __construct(private FundBalanceService $fundBalances) {}

    /**
     * @return array{
     *     year: int, income: int, expense: int, deductible: int,
     *     non_deductible: int, taxable: int, tax_fund: int,
     *     deductible_rows: array<int, array{name: string, amount: int}>,
     *     non_deductible_rows: array<int, array{name: string, amount: int}>,
     * }
     */
    public function report(int $year): array
    {
        $from = CarbonImmutable::create($year, 1, 1)->toDateString();
        $to = CarbonImmutable::create($year, 12, 31)->toDateString();

        $income = (int) Transaction::query()
            ->where('type', 'income')
            ->between($from, $to)
            ->sum('amount');

        $deductible = $this->expensesByCategory($from, $to, true);
        $nonDeductible = $this->expensesByCategory($from, $to, false);

        $deductibleTotal = array_sum(array_column($deductible, 'amount'));
        $nonDeductibleTotal = array_sum(array_column($nonDeductible, 'amount'));

        return [
            'year' => $year,
            'income' => $income,
            'expense' => $deductibleTotal + $nonDeductibleTotal,
            'deductible' => $deductibleTotal,
            'non_deductible' => $nonDeductibleTotal,
            // Can go negative: a loss-making year is a real answer.
            'taxable' => $income - $deductibleTotal,
            'tax_fund' => $this->taxFundBalance(),
            'deductible_rows' => $deductible,
            'non_deductible_rows' => $nonDeductible,
        ];
    }

    /**
     * The years that have any transactions, newest first, for the picker.
     *
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        $years = Transaction::query()
            ->toBase()
            ->selectRaw('DISTINCT SUBSTR(transactions.date, 1, 4) AS yr')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->map(fn ($year) => (int) $year)
            ->all();

        $thisYear = CarbonImmutable::today()->year;

        if (! in_array($thisYear, $years, true)) {
            array_unshift($years, $thisYear);
        }

        return $years;
    }

    /**
     * Expenses for the year, split by whether they are claimable.
     *
     * @return array<int, array{name: string, amount: int}>
     */
    private function expensesByCategory(string $from, string $to, bool $deductible): array
    {
        $rows = Transaction::query()
            ->where('type', 'expense')
            ->where('tax_deductible', $deductible)
            ->between($from, $to)
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

    private function taxFundBalance(): int
    {
        $fund = Fund::query()->where('slug', 'tax')->first();

        return $fund === null ? 0 : $this->fundBalances->for($fund);
    }
}
