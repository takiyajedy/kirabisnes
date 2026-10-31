<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Account balances are derived from the transactions, never stored, so they
 * cannot drift out of sync (PLAN.md section 5).
 *
 *   balance = opening_balance
 *           + income + capital_injection + transfers in
 *           - expense - owner_draw - transfers out
 */
class AccountBalanceService
{
    public function for(Account $account): int
    {
        return $this->all()[$account->id] ?? $account->opening_balance;
    }

    /**
     * Every account balance in two aggregate queries.
     *
     * @return array<int, int> Account id => sen.
     */
    public function all(): array
    {
        $balances = Account::query()
            ->pluck('opening_balance', 'id')
            ->map(fn (int $opening) => $opening)
            ->all();

        $movements = Transaction::query()
            ->select('account_id')
            ->selectRaw(
                "SUM(CASE WHEN type IN ('income', 'capital_injection') THEN amount ELSE 0 END)
                 - SUM(CASE WHEN type IN ('expense', 'owner_draw', 'transfer') THEN amount ELSE 0 END)
                 AS delta",
            )
            ->groupBy('account_id')
            ->pluck('delta', 'account_id');

        foreach ($movements as $accountId => $delta) {
            $balances[$accountId] = ($balances[$accountId] ?? 0) + (int) $delta;
        }

        $incoming = Transaction::query()
            ->whereNotNull('to_account_id')
            ->where('type', 'transfer')
            ->groupBy('to_account_id')
            ->pluck(DB::raw('SUM(amount)'), 'to_account_id');

        foreach ($incoming as $accountId => $amount) {
            $balances[$accountId] = ($balances[$accountId] ?? 0) + (int) $amount;
        }

        return $balances;
    }

    /**
     * The money on hand across every account.
     */
    public function total(): int
    {
        return array_sum($this->all());
    }

    /**
     * The money on hand across the active accounts only.
     */
    public function totalActive(): int
    {
        $balances = $this->all();

        $activeIds = Account::query()->active()->pluck('id')->all();

        return array_sum(array_intersect_key($balances, array_flip($activeIds)));
    }
}
