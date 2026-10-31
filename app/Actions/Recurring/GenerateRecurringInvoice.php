<?php

namespace App\Actions\Recurring;

use App\Actions\Invoices\StoreInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\RecurringItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Drafts the invoice a client billing item is due to produce.
 *
 * It comes out as a draft on purpose: an automatic charge still deserves a
 * look before it goes to a client (PLAN.md phase 4).
 *
 * The invoice is dated on the charge date rather than today, which is both
 * what the client expects to read and what makes a second run harmless: one
 * invoice per item per charge date, and the pair is checked before writing.
 */
class GenerateRecurringInvoice
{
    public function __construct(private StoreInvoice $storeInvoice) {}

    /**
     * Null when this charge has already been invoiced.
     */
    public function handle(RecurringItem $item, ?CarbonImmutable $on = null): ?Invoice
    {
        $on ??= $item->next_date;

        if ($item->client_id === null) {
            return null;
        }

        if ($this->alreadyInvoiced($item, $on)) {
            return null;
        }

        return DB::transaction(fn (): Invoice => $this->storeInvoice->handle([
            'client_id' => $item->client_id,
            'project_id' => $item->project_id,
            'recurring_item_id' => $item->id,
            'issue_date' => $on->toDateString(),
            'status' => InvoiceStatus::Draft->value,
            // The document follows the client, not the owner (PLAN.md 3.5).
            'locale' => $item->client->preferred_locale->value,
            'notes' => $item->description,
            'items' => [[
                'description' => $item->name,
                'quantity' => 1,
                'unit_price' => $item->amount,
            ]],
        ]));
    }

    /**
     * Has this item already produced an invoice for this charge date?
     *
     * Soft deleted invoices count: a draft the owner threw away was a
     * decision, and regenerating it would undo that.
     */
    public function alreadyInvoiced(RecurringItem $item, CarbonImmutable $on): bool
    {
        return Invoice::withTrashed()
            ->where('recurring_item_id', $item->id)
            ->whereDate('issue_date', $on->toDateString())
            ->exists();
    }
}
