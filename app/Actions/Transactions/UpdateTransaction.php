<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Services\FundAllocationService;
use Illuminate\Support\Facades\DB;

class UpdateTransaction
{
    public function __construct(
        private FundAllocationService $allocation,
        private StoreTransaction $store,
    ) {}

    /**
     * Editing a transaction throws away its old fund movements and works them
     * out again, so the funds always reflect the current figures.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Transaction $transaction, array $attributes): Transaction
    {
        $attributes = $this->store->prepare($attributes);

        return DB::transaction(function () use ($transaction, $attributes): Transaction {
            $transaction->update($attributes);

            $this->allocation->sync($transaction->refresh());

            return $transaction;
        });
    }
}
