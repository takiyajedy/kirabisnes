<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ms' => $this->getTranslation('name', 'ms'),
            'name_en' => $this->getTranslation('name', 'en'),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'default_fund_id' => $this->default_fund_id,
            'tax_deductible_default' => $this->tax_deductible_default,
            'color' => $this->color,
            'is_system' => $this->is_system,
            'transactions_count' => $this->whenCounted('transactions'),
        ];
    }
}
