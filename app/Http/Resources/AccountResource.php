<?php

namespace App\Http\Resources;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'bank_name' => $this->bank_name,
            'opening_balance' => $this->opening_balance,
            'balance' => $this->whenNotNull($this->resource->balance),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
