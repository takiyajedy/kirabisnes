<?php

namespace App\Actions\Recurring;

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Models\RecurringItem;
use App\Models\Transaction;
use App\Services\RecurringScheduleService;
use Illuminate\Support\Facades\DB;

/**
 * Records a recurring own cost as paid: an ordinary expense, then the item
 * moves on to its next charge date (PLAN.md phase 4).
 *
 * The expense goes through StoreTransaction, so the fund it is taken from is
 * resolved the same way as for one typed in by hand.
 */
class PayRecurringCost
{
    public function __construct(
        private StoreTransaction $store,
        private RecurringScheduleService $schedule,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  amount, date, account_id, category_id
     */
    public function handle(RecurringItem $item, array $attributes = []): Transaction
    {
        return DB::transaction(function () use ($item, $attributes): Transaction {
            $expense = $this->store->handle([
                'type' => TransactionType::Expense->value,
                'account_id' => $attributes['account_id'] ?? $item->account_id,
                'amount' => $attributes['amount'] ?? $item->amount,
                'date' => $attributes['date'] ?? $item->next_date->toDateString(),
                'description' => $attributes['description'] ?? $item->name,
                'category_id' => $attributes['category_id'] ?? $item->category_id,
                'project_id' => $item->project_id,
                'recurring_item_id' => $item->id,
                // The column is not nullable, and the category knows the
                // usual answer for this kind of cost.
                'tax_deductible' => $attributes['tax_deductible']
                    ?? $item->category->tax_deductible_default
                    ?? false,
            ]);

            // Paying it is what moves the item along; the daily command only
            // reminds, so a cost is never skipped by a reminder going out.
            $this->schedule->advance($item);

            // The next charge is a new period, so it may be reminded about.
            $item->update(['last_reminded_at' => null]);

            return $expense;
        });
    }
}
