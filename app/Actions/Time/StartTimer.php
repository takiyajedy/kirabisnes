<?php

namespace App\Actions\Time;

use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Starts the timer on a project (PLAN.md phase 5).
 *
 * Only one timer runs at a time. Starting a second one stops the first
 * rather than refusing: the owner has plainly moved on to other work, and
 * leaving two running would double-count the same minutes.
 */
class StartTimer
{
    public function __construct(private StopTimer $stop) {}

    public function handle(Project $project, ?string $note = null): TimeEntry
    {
        return DB::transaction(function () use ($project, $note): TimeEntry {
            $running = TimeEntry::query()->running()->first();

            if ($running !== null) {
                $this->stop->handle($running);
            }

            return TimeEntry::query()->create([
                'project_id' => $project->id,
                'started_at' => CarbonImmutable::now(),
                'ended_at' => null,
                'minutes' => null,
                'note' => $note,
                'billable' => true,
            ]);
        });
    }

    /**
     * The timer currently running, if any.
     */
    public static function current(): ?TimeEntry
    {
        return TimeEntry::query()->running()->with('project')->first();
    }
}
