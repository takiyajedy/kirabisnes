<?php

namespace App\Services;

use App\Enums\FundMovementType;
use App\Enums\TransactionType;
use App\Models\Fund;
use App\Models\FundMovement;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Keeps `fund_movements` in step with a transaction.
 *
 * Money is only ever integer sen (PLAN.md 3.1). The split uses integer maths
 * and gives the rounding remainder to the first fund, so the movements always
 * add up to the transaction amount exactly.
 */
class FundAllocationService
{
    /**
     * Rebuild every fund movement for a transaction.
     *
     * Callers must wrap this in a database transaction together with the
     * change to the transaction row itself (PLAN.md 3.2).
     */
    public function sync(Transaction $transaction): void
    {
        $this->clear($transaction);

        match (true) {
            $transaction->type->spendsFromFund() => $this->spend($transaction),
            $transaction->type === TransactionType::Income,
            $transaction->type === TransactionType::CapitalInjection => $this->allocate($transaction),
            // A transfer between own accounts moves no money between funds.
            default => null,
        };
    }

    /**
     * Split an incoming amount across the funds.
     *
     * Income is divided by percentage; a capital injection goes wholly into
     * the fund it names (the capital fund by default).
     */
    public function allocate(Transaction $transaction): void
    {
        $shares = $transaction->type === TransactionType::CapitalInjection
            ? $this->shareForSingleFund($transaction)
            : self::split($transaction->amount, $this->activeFunds());

        foreach ($shares as $fundId => $amount) {
            if ($amount === 0) {
                continue;
            }

            $this->record($transaction, $fundId, FundMovementType::Allocate, $amount);
        }
    }

    /**
     * Take an expense or an owner draw out of a single fund.
     */
    public function spend(Transaction $transaction): void
    {
        $fundId = $transaction->fund_id;

        if ($fundId === null || $transaction->amount === 0) {
            return;
        }

        // Negative: money leaves the fund.
        $this->record($transaction, $fundId, FundMovementType::Spend, -$transaction->amount);
    }

    /**
     * Drop every movement belonging to a transaction, so it can be rebuilt.
     */
    public function clear(Transaction $transaction): void
    {
        FundMovement::query()
            ->where('transaction_id', $transaction->id)
            ->delete();
    }

    /**
     * Divide an amount of sen across funds by percentage.
     *
     * The remainder left by integer division goes to the first fund, which is
     * the one with the smallest sort_order.
     *
     * @param  Collection<int, Fund>  $funds  Ordered by sort_order.
     * @return array<int, int> Fund id => sen.
     */
    public static function split(int $amount, Collection $funds): array
    {
        if ($funds->isEmpty() || $amount === 0) {
            return [];
        }

        $shares = [];
        $allocated = 0;

        foreach ($funds as $fund) {
            // Basis points keep the percentage in integers: 30.00% => 3000.
            $share = intdiv($amount * $fund->percentageInBasisPoints(), 10_000);

            $shares[$fund->id] = $share;
            $allocated += $share;
        }

        $remainder = $amount - $allocated;

        if ($remainder !== 0) {
            $first = $funds->first();
            $shares[$first->id] += $remainder;
        }

        return $shares;
    }

    /**
     * The funds an income is split across, in allocation order.
     *
     * @return Collection<int, Fund>
     */
    public function activeFunds(): Collection
    {
        return Fund::query()->active()->ordered()->get();
    }

    /**
     * @return array<int, int>
     */
    private function shareForSingleFund(Transaction $transaction): array
    {
        $fundId = $transaction->fund_id ?? Fund::query()
            ->where('slug', 'capital')
            ->value('id');

        if ($fundId === null) {
            return [];
        }

        return [$fundId => $transaction->amount];
    }

    private function record(
        Transaction $transaction,
        int $fundId,
        FundMovementType $type,
        int $amount,
    ): void {
        FundMovement::query()->create([
            'fund_id' => $fundId,
            'transaction_id' => $transaction->id,
            'type' => $type,
            'amount' => $amount,
            'date' => $transaction->date,
            'note' => $transaction->description,
        ]);
    }
}
