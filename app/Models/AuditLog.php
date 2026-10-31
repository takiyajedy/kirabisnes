<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the audit trail (PLAN.md phase 6).
 *
 * @property int $id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string $event
 * @property string|null $subject
 * @property int|null $user_id
 * @property string|null $user_name
 * @property array<string, array{from: mixed, to: mixed}>|null $changes
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'auditable_type', 'auditable_id', 'event', 'subject', 'user_id',
    'user_name', 'changes',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The short name of the model this line is about, for the UI.
     */
    public function subjectType(): string
    {
        return class_basename($this->auditable_type);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
