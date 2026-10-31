<?php

namespace App\Actions\Invoices;

use App\Actions\Transactions\StoreTransaction;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * A payment against an invoice is an ordinary income transaction that knows
 * which invoice, client and project it belongs to.
 *
 * Recording it through StoreTransaction is what makes the fund allocation
 * happen: there is no second path for invoice money, so the split is the
 * same whether the income was typed in by hand or came from an invoice
 * (PLAN.md phase 3).
 */
class RecordInvoicePayment
{
    public function __construct(
        private StoreTransaction $store,
        private RefreshInvoiceState $refresh,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  account_id, amount, date, reference, category_id
     */
    public function handle(Invoice $invoice, array $attributes): Transaction
    {
        return DB::transaction(function () use ($invoice, $attributes): Transaction {
            $payment = $this->store->handle([
                'type' => TransactionType::Income->value,
                'account_id' => $attributes['account_id'],
                'amount' => $attributes['amount'],
                'date' => $attributes['date'],
                'description' => $attributes['description']
                    ?? __('invoice.payment_description', ['number' => $invoice->number]),
                'reference' => $attributes['reference'] ?? $invoice->number,
                'category_id' => $attributes['category_id'] ?? null,
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'project_id' => $invoice->project_id,
            ]);

            $this->refresh->handle($invoice);

            return $payment;
        });
    }
}
