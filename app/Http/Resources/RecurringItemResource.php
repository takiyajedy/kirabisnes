<?php

namespace App\Http\Resources;

use App\Models\RecurringItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecurringItem
 */
class RecurringItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'name' => $this->name,
            'description' => $this->description,
            'amount' => $this->amount,
            'interval' => $this->interval->value,
            'interval_label' => $this->interval->label(),
            'next_date' => $this->next_date->toDateString(),
            'anchor_day' => $this->anchor_day,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->displayName()),
            'project_id' => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project?->name),
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn () => $this->account?->name),
            'auto_generate' => $this->auto_generate,
            'remind_days_before' => $this->remind_days_before,
            'is_active' => $this->is_active,
            // Whether the warning window has opened, so the row can say so.
            'is_due' => $this->isDue(),
            'reminds_on' => $this->remindsOn()->toDateString(),
        ];
    }
}
