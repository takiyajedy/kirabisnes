<?php

namespace App\Http\Resources;

use App\Models\InvoiceItem;
use App\Models\QuotationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A quotation line and an invoice line carry the same fields.
 *
 * @mixin InvoiceItem|QuotationItem
 */
class DocumentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            // Decimal, because 2.5 hours is a real thing to bill for.
            'quantity' => (float) $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'sort_order' => $this->sort_order,
        ];
    }
}
