<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Fund;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Works out how much the owner can safely pay themselves (PLAN.md phase 2).
 *
 *   safe draw = min(owner_pay fund balance, 3-month average income × share)
 *
 * The fund balance is the hard limit: it is money already set aside. The
 * average keeps a single large payment from being drawn in one go, since the
 * following months still have to be covered.
 *
 * The average spans the current month and the two before it, divided by
 * three. The current month is usually only part over, so the average leans
 * low — the safe direction for a figure that suggests taking money out.
 */
class OwnerPayService
{
    /** How many months the income average spans. */
    public const MONTHS = 3;

    public function __construct(private FundBalanceService $balances) {}

    /**
     * The owner pay fund. It is a system fund the seeder always creates and
     * the UI will not let you delete, so its absence is a broken install
     * rather than a case to paper over.
     */
    public function fund(): Fund
    {
        return Fund::query()->where('slug', 'owner_pay')->firstOrFail();
    }

    public function balance(?Fund $fund = null): int
    {
        return $this->balances->for($fund ?? $this->fund());
    }

    /**
     * Average monthly income over the last three calendar months.
     */
    public function averageMonthlyIncome(int $months = self::MONTHS): int
    {
        $today = CarbonImmutable::today();
        $from = $today->startOfMonth()->subMonths($months - 1);

        $total = (int) Transaction::query()
            ->where('type', TransactionType::Income)
            ->between($from->toDateString(), $today->endOfMonth()->toDateString())
            ->sum('amount');

        return intdiv($total, $months);
    }

    /**
     * The amount the page suggests taking, in sen.
     */
    public function safeDraw(?Fund $fund = null): int
    {
        $fund ??= $this->fund();

        // Basis points: the percentage never becomes a float (PLAN.md 3.1).
        $share = intdiv(
            $this->averageMonthlyIncome() * $fund->percentageInBasisPoints(),
            10_000,
        );

        return max(0, min($this->balance($fund), $share));
    }

    /**
     * Everything already drawn in the current calendar year.
     */
    public function drawnThisYear(): int
    {
        $year = CarbonImmutable::today();

        return (int) Transaction::query()
            ->where('type', TransactionType::OwnerDraw)
            ->between(
                $year->startOfYear()->toDateString(),
                $year->endOfYear()->toDateString(),
            )
            ->sum('amount');
    }

    /**
     * The owner draws so far, newest first.
     *
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function history(int $perPage = 10): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account', 'fund'])
            ->where('type', TransactionType::OwnerDraw)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
