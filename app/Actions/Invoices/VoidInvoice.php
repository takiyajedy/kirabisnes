<?php

namespace App\Actions\Invoices;

use App\Actions\Transactions\DeleteTransaction;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * An invoice with payments against it is voided, never deleted: the number
 * was given to a client and the money really moved (PLAN.md phase 3).
 *
 * Voiding it also removes the payments it caused, because the income never
 * belonged to the business — leaving them would keep money in the funds that
 * the invoice says was never earned.
 */
class VoidInvoice
{
    public function __construct(private DeleteTransaction $deleteTransaction) {}

    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->payments->each(
                fn (Transaction $payment) => $this->deleteTransaction->handle($payment),
            );

            $invoice->update([
                'status' => InvoiceStatus::Void,
                'paid_amount' => 0,
                'paid_at' => null,
            ]);

            return $invoice;
        });
    }
}
