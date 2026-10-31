<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
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
            'quotation_id' => $this->quotation_id,
            'quotation_number' => $this->whenLoaded('quotation', fn () => $this->quotation?->number),
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_tone' => $this->status->tone(),
            'is_editable' => $this->status->isEditable(),
            'is_late' => $this->isLate(),
            'locale' => $this->locale->value,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'outstanding' => $this->outstanding(),
            'notes' => $this->notes,
            'terms' => $this->terms,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toDateString(),
            // resolve() rather than handing the collection over as-is: a
            // nested resource collection serialises to {"data": [...]}, and
            // the page expects a plain list.
            'items' => $this->whenLoaded(
                'items',
                fn () => DocumentItemResource::collection($this->items)->resolve(),
            ),
            'payments' => $this->whenLoaded(
                'payments',
                fn () => TransactionResource::collection($this->payments)->resolve(),
            ),
        ];
    }
}
