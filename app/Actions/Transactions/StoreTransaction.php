<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Jobs\PushOwnerDraw;
use App\Models\Transaction;
use App\Services\FundAllocationService;
use Illuminate\Support\Facades\DB;

class StoreTransaction
{
    public function __construct(
        private FundAllocationService $allocation,
        private ResolveTransactionFund $resolveFund,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Transaction
    {
        $attributes = $this->prepare($attributes);

        // The transaction row and its fund movements must land together.
        $transaction = DB::transaction(function () use ($attributes): Transaction {
            $transaction = Transaction::query()->create($attributes);

            $this->allocation->sync($transaction);

            return $transaction;
        });

        // Paying yourself is income on your personal books, so it is pushed
        // to the tracker that keeps them — after the commit, because there
        // is nothing to tell anyone about if the write rolled back.
        if ($transaction->type === TransactionType::OwnerDraw) {
            PushOwnerDraw::dispatch($transaction);
        }

        return $transaction;
    }

    /**
     * Normalise the attributes so a transaction never carries fields that do
     * not belong to its type.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function prepare(array $attributes): array
    {
        $type = $attributes['type'] instanceof TransactionType
            ? $attributes['type']
            : TransactionType::from($attributes['type']);

        $attributes['type'] = $type;

        $attributes['fund_id'] = $this->resolveFund->handle(
            $type,
            $attributes['fund_id'] ?? null,
            $attributes['category_id'] ?? null,
        );

        if (! $type->hasCategory()) {
            $attributes['category_id'] = null;
        }

        if (! $type->isTransfer()) {
            $attributes['to_account_id'] = null;
        }

        if ($type !== TransactionType::Expense) {
            $attributes['tax_deductible'] = false;
            $attributes['receipt_path'] = $attributes['receipt_path'] ?? null;
        }

        return $attributes;
    }
}
