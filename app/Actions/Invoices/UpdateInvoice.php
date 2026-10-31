<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

class UpdateInvoice
{
    public function __construct(private RefreshInvoiceState $refresh) {}

    /**
     * The line items are replaced rather than matched up one by one: an
     * invoice is a short list the owner has just re-entered, and keeping ids
     * alive adds a reconciliation step that buys nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Invoice $invoice, array $attributes): Invoice
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $attributes['items'] ?? [];
        unset($attributes['items'], $attributes['number'], $attributes['paid_amount']);

        $totals = DocumentTotals::forItems($items, (int) ($attributes['discount'] ?? 0));

        return DB::transaction(function () use ($invoice, $attributes, $totals): Invoice {
            $invoice->update([
                ...$attributes,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
            ]);

            $invoice->items()->delete();
            $invoice->items()->createMany($totals['items']);

            // Changing the total can change whether the invoice counts as
            // paid, so the status is worked out again.
            return $this->refresh->handle($invoice->refresh());
        });
    }
}
