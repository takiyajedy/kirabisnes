<?php

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeEntry
 */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_name' => $this->whenLoaded('project', fn () => $this->project->name),
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'minutes' => $this->minutes,
            // What a running timer has clocked up so far, so the page has
            // something to show before the first tick.
            'elapsed_minutes' => $this->elapsedMinutes(),
            'is_running' => $this->isRunning(),
            'note' => $this->note,
            'billable' => $this->billable,
        ];
    }
}
