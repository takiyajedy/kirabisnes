<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * Records that an invoice has gone out. An invoice that already has money
 * against it keeps its payment status: being sent again does not unpay it.
 */
class MarkInvoiceSent
{
    public function handle(Invoice $invoice): Invoice
    {
        $invoice->sent_at ??= now();

        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->status = InvoiceStatus::Sent;
        }

        $invoice->save();

        return $invoice;
    }
}
