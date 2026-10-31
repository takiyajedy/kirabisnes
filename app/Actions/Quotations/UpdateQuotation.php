<?php

namespace App\Actions\Quotations;

use App\Models\Quotation;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;

class UpdateQuotation
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Quotation $quotation, array $attributes): Quotation
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $attributes['items'] ?? [];
        unset($attributes['items'], $attributes['number'], $attributes['converted_invoice_id']);

        $totals = DocumentTotals::forItems($items, (int) ($attributes['discount'] ?? 0));

        return DB::transaction(function () use ($quotation, $attributes, $totals): Quotation {
            $quotation->update([
                ...$attributes,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
            ]);

            $quotation->items()->delete();
            $quotation->items()->createMany($totals['items']);

            return $quotation->refresh();
        });
    }
}
