<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn () => $this->account->name),
            'to_account_id' => $this->to_account_id,
            'to_account_name' => $this->whenLoaded('toAccount', fn () => $this->toAccount?->name),
            // Always positive; the type says which way the money went.
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'fund_id' => $this->fund_id,
            'fund_name' => $this->whenLoaded('fund', fn () => $this->fund?->name),
            'tax_deductible' => $this->tax_deductible,
            // Receipts live on the private disk, so they are served through a
            // signed-in route rather than a public URL.
            'receipt_url' => $this->receipt_path === null
                ? null
                : route('transactions.receipt', $this->id),
            'has_receipt' => $this->receipt_path !== null,
        ];
    }
}
