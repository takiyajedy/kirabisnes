<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'display_name' => $this->displayName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'reg_no' => $this->reg_no,
            'notes' => $this->notes,
            // The language this client's documents are written in, which is
            // not the language the owner reads the app in.
            'preferred_locale' => $this->preferred_locale->value,
            'preferred_locale_label' => $this->preferred_locale->nativeLabel(),
            'projects_count' => $this->whenCounted('projects'),
            'summary' => $this->whenNotNull($this->resource->summary),
        ];
    }
}
