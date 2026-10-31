<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Services\FundAllocationService;
use Illuminate\Support\Facades\DB;

class DeleteTransaction
{
    public function __construct(private FundAllocationService $allocation) {}

    /**
     * The transaction is soft deleted, but its fund movements go for good:
     * a deleted transaction must not keep affecting a fund balance.
     */
    public function handle(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $this->allocation->clear($transaction);

            $transaction->delete();
        });
    }
}
