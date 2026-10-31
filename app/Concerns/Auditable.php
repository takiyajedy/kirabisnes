<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit trail for a model (PLAN.md phase 6).
 *
 * This hangs off Eloquent's own events rather than off the Actions, so there
 * is no code path that can change a transaction or an invoice without the
 * change being recorded — including a `->update()` typed into tinker.
 *
 * Only the fields that actually moved are kept, each with its before and
 * after. A log that repeated the whole row would be unreadable, and the
 * question an audit trail answers is "what changed".
 */
trait Auditable
{
    /**
     * Which fields are worth a line. Timestamps and derived columns are not:
     * they move on their own and say nothing about intent.
     *
     * @return array<int, string>
     */
    abstract public function auditedAttributes(): array;

    /**
     * How this record reads in a list, kept because the row it names may be
     * gone by the time anyone looks.
     */
    abstract public function auditSubject(): string;

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => self::writeAudit($model, 'created'));

        static::updated(function (Model $model): void {
            $changes = self::changesFor($model);

            // A save that moved nothing worth recording is not an event.
            if ($changes !== []) {
                self::writeAudit($model, 'updated', $changes);
            }
        });

        static::deleted(fn (Model $model) => self::writeAudit($model, 'deleted'));
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>|null  $changes
     */
    private static function writeAudit(Model $model, string $event, ?array $changes = null): void
    {
        /** @var self $model */
        $user = Auth::user();

        AuditLog::query()->create([
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'subject' => $model->auditSubject(),
            'user_id' => $user?->getKey(),
            // The name is copied: an audit line should still read in full
            // after the account that made it is gone.
            'user_name' => $user?->name,
            'changes' => $changes,
        ]);
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private static function changesFor(Model $model): array
    {
        /** @var self $model */
        $audited = $model->auditedAttributes();
        $changes = [];

        foreach ($model->getChanges() as $field => $to) {
            if (! in_array($field, $audited, true)) {
                continue;
            }

            $changes[$field] = [
                'from' => self::readable($model->getOriginal($field)),
                'to' => self::readable($to),
            ];
        }

        return $changes;
    }

    /**
     * Enums and dates have to survive a round trip through JSON.
     */
    private static function readable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_scalar($value) || $value === null => $value,
            default => (string) json_encode($value),
        };
    }
}
