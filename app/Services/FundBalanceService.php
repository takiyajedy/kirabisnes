<?php

namespace App\Services;

use App\Models\Fund;
use App\Models\FundMovement;

/**
 * A fund balance is the sum of its movements, which are signed: positive in,
 * negative out. Funds are allowed to go negative — the UI shows a warning
 * rather than blocking it (PLAN.md section 5).
 */
class FundBalanceService
{
    public function for(Fund $fund): int
    {
        return $this->all()[$fund->id] ?? 0;
    }

    /**
     * @return array<int, int> Fund id => sen.
     */
    public function all(): array
    {
        $balances = Fund::query()->pluck('id')
            ->mapWithKeys(fn (int $id) => [$id => 0])
            ->all();

        $sums = FundMovement::query()
            ->select('fund_id')
            ->selectRaw('SUM(amount) AS balance')
            ->groupBy('fund_id')
            ->pluck('balance', 'fund_id');

        foreach ($sums as $fundId => $balance) {
            $balances[$fundId] = (int) $balance;
        }

        return $balances;
    }

    public function total(): int
    {
        return array_sum($this->all());
    }
}
