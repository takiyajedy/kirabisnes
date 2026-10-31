<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stretch of work on a project (PLAN.md phase 5).
 *
 * @property int $id
 * @property int $project_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property int|null $minutes
 * @property string|null $note
 * @property bool $billable
 */
#[Fillable(['project_id', 'started_at', 'ended_at', 'minutes', 'note', 'billable'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'billable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * How long this entry has run, including a timer still going.
     *
     * Rounded to the nearest minute: billing by the second is not a thing,
     * and a stored figure that disagrees with the one on screen would be
     * worse than either.
     */
    public function elapsedMinutes(?CarbonImmutable $now = null): int
    {
        if ($this->minutes !== null) {
            return $this->minutes;
        }

        $end = $this->ended_at ?? $now ?? CarbonImmutable::now();

        return (int) round($this->started_at->diffInSeconds($end) / 60);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->where('billable', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('started_at')->orderByDesc('id');
    }
}
