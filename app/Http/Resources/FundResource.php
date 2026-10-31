<?php

namespace App\Http\Resources;

use App\Models\Fund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Fund
 */
class FundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Resolved in the current UI language by spatie/laravel-translatable.
            'name' => $this->name,
            'slug' => $this->slug,
            'percentage' => (float) $this->percentage,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'balance' => $this->whenNotNull($this->resource->balance),
        ];
    }
}
