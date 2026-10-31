<?php

namespace App\Actions\Invoices;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\DocumentNumberService;
use App\Support\BusinessProfile;
use App\Support\DocumentTotals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StoreInvoice
{
    public function __construct(private DocumentNumberService $numbers) {}

    /**
     * @param  array<string, mixed>  $attributes  Includes an `items` list.
     */
    public function handle(array $attributes): Invoice
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $attributes['items'] ?? [];
        unset($attributes['items']);

        $totals = DocumentTotals::forItems($items, (int) ($attributes['discount'] ?? 0));

        return DB::transaction(function () use ($attributes, $totals): Invoice {
            $invoice = Invoice::query()->create([
                ...$attributes,
                // Taken inside the transaction, so the lock on the counter row
                // is held until this invoice actually exists.
                'number' => $attributes['number'] ?? $this->numbers->next(DocumentType::Invoice),
                'due_date' => $attributes['due_date'] ?? self::defaultDueDate($attributes['issue_date']),
                'status' => $attributes['status'] ?? InvoiceStatus::Draft->value,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'paid_amount' => 0,
            ]);

            $invoice->items()->createMany($totals['items']);

            return $invoice;
        });
    }

    /**
     * Payment terms come from the business settings, so the owner sets them
     * once rather than on every invoice.
     */
    public static function defaultDueDate(string $issueDate): string
    {
        $days = (int) BusinessProfile::all()['default_payment_terms_days'];

        return CarbonImmutable::parse($issueDate)->addDays($days)->toDateString();
    }
}
