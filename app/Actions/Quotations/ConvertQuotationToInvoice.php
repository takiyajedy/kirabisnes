<?php

namespace App\Actions\Quotations;

use App\Actions\Invoices\StoreInvoice;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an accepted quotation into an invoice (PLAN.md phase 3).
 *
 * The lines and the document language are copied rather than shared: the
 * quotation is a record of what was offered on the day, and editing the
 * invoice afterwards must not rewrite history. The link between them is kept
 * both ways, so either document can be found from the other.
 */
class ConvertQuotationToInvoice
{
    public function __construct(private StoreInvoice $storeInvoice) {}

    public function handle(Quotation $quotation, ?string $issueDate = null): Invoice
    {
        if ($quotation->isConverted()) {
            // Converting twice would give the client two invoices for one job.
            throw ValidationException::withMessages([
                'quotation' => __('quotation.error.already_converted'),
            ]);
        }

        $issueDate ??= now()->toDateString();

        return DB::transaction(function () use ($quotation, $issueDate): Invoice {
            $invoice = $this->storeInvoice->handle([
                'client_id' => $quotation->client_id,
                'project_id' => $quotation->project_id,
                'quotation_id' => $quotation->id,
                'issue_date' => $issueDate,
                'locale' => $quotation->locale->value,
                'discount' => $quotation->discount,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'items' => $quotation->items->map(fn (QuotationItem $item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ])->all(),
            ]);

            $quotation->update([
                'status' => QuotationStatus::Accepted,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }
}
