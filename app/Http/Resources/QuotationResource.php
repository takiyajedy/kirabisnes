<?php

namespace App\Http\Resources;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client->displayName()),
            'project_id' => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project?->name),
            'issue_date' => $this->issue_date->toDateString(),
            'valid_until' => $this->valid_until->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'is_editable' => $this->status->isEditable() && ! $this->isConverted(),
            'has_lapsed' => $this->hasLapsed(),
            // The language the document itself is written in.
            'locale' => $this->locale->value,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'converted_invoice_id' => $this->converted_invoice_id,
            'converted_invoice_number' => $this->whenLoaded(
                'convertedInvoice',
                fn () => $this->convertedInvoice?->number,
            ),
            // resolve() rather than handing the collection over as-is: a
            // nested resource collection serialises to {"data": [...]}, and
            // the page expects a plain list.
            'items' => $this->whenLoaded(
                'items',
                fn () => DocumentItemResource::collection($this->items)->resolve(),
            ),
        ];
    }
}
