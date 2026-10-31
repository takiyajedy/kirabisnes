<?php

namespace App\Actions\Quotations;

use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Services\DocumentNumberService;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

class StoreQuotation
{
    public function __construct(private DocumentNumberService $numbers) {}

    /**
     * @param  array<string, mixed>  $attributes  Includes an `items` list.
     */
    public function handle(array $attributes): Quotation
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $attributes['items'] ?? [];
        unset($attributes['items']);

        $totals = DocumentTotals::forItems($items, (int) ($attributes['discount'] ?? 0));

        return DB::transaction(function () use ($attributes, $totals): Quotation {
            $quotation = Quotation::query()->create([
                ...$attributes,
                'number' => $attributes['number'] ?? $this->numbers->next(DocumentType::Quotation),
                'status' => $attributes['status'] ?? QuotationStatus::Draft->value,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
            ]);

            $quotation->items()->createMany($totals['items']);

            return $quotation;
        });
    }
}
