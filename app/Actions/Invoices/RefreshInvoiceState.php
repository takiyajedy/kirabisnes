<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * Brings an invoice's paid amount and status back in line with the payments
 * recorded against it (PLAN.md phase 3).
 *
 * `paid_amount` is a running total of the payment transactions, so it is
 * recomputed from them rather than added to: a payment that is edited or
 * deleted then cannot leave the invoice claiming money it never had.
 *
 *   paid = 0              -> back to sent, or draft if it was never sent
 *   0 < paid < total      -> partial, or overdue if the due date has passed
 *   paid >= total         -> paid
 *
 * A voided invoice is left alone: it is a record of something that did not
 * happen, and its status is not up for recalculation.
 */
class RefreshInvoiceState
{
    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return $invoice;
        }

        $paid = (int) $invoice->payments()->sum('amount');

        $invoice->paid_amount = $paid;
        $invoice->status = $this->statusFor($invoice, $paid);
        $invoice->paid_at = $invoice->status === InvoiceStatus::Paid
            ? ($invoice->paid_at ?? $invoice->payments()->max('date'))
            : null;

        $invoice->save();

        return $invoice;
    }

    private function statusFor(Invoice $invoice, int $paid): InvoiceStatus
    {
        if ($paid >= $invoice->total && $invoice->total > 0) {
            return InvoiceStatus::Paid;
        }

        if ($paid > 0) {
            // Part paid and late at the same time: the plan has both rules
            // applying, and the daily overdue command would set this anyway.
            // Deciding it here keeps the status from flapping between the two.
            return $invoice->isPastDue()
                ? InvoiceStatus::Overdue
                : InvoiceStatus::Partial;
        }

        // Nothing paid: the invoice goes back to where it was before any
        // payment, which depends only on whether it has been sent.
        if ($invoice->sent_at !== null) {
            // isLate() reads the current status, which is the one being
            // replaced here, so the due date is checked on its own.
            return $invoice->isPastDue()
                ? InvoiceStatus::Overdue
                : InvoiceStatus::Sent;
        }

        return InvoiceStatus::Draft;
    }
}
