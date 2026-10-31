<?php

namespace App\Models;

use App\Enums\RecurringInterval;
use App\Enums\RecurringKind;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property RecurringKind $kind
 * @property int|null $client_id
 * @property int|null $project_id
 * @property int|null $category_id
 * @property int|null $account_id
 * @property string $name
 * @property string|null $description
 * @property int $amount
 * @property RecurringInterval $interval
 * @property CarbonImmutable $next_date
 * @property int $anchor_day
 * @property bool $auto_generate
 * @property int $remind_days_before
 * @property CarbonImmutable|null $last_reminded_at
 * @property bool $is_active
 */
#[Fillable([
    'kind', 'client_id', 'project_id', 'category_id', 'account_id', 'name',
    'description', 'amount', 'interval', 'next_date', 'anchor_day',
    'auto_generate', 'remind_days_before', 'last_reminded_at', 'is_active',
])]
class RecurringItem extends Model
{
    /** @use HasFactory<RecurringItemFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'kind' => RecurringKind::class,
            'interval' => RecurringInterval::class,
            'amount' => 'integer',
            'next_date' => 'date',
            'anchor_day' => 'integer',
            'auto_generate' => 'boolean',
            'remind_days_before' => 'integer',
            'last_reminded_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** The draft invoices this item has produced. */
    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** The expenses recorded against this item. */
    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The first day the owner should hear about the next charge.
     */
    public function remindsOn(): CarbonImmutable
    {
        return $this->next_date->subDays($this->remind_days_before);
    }

    /**
     * Is the next charge close enough to act on?
     */
    public function isDue(?CarbonImmutable $on = null): bool
    {
        $on ??= CarbonImmutable::today();

        return $this->is_active && $this->remindsOn()->lessThanOrEqualTo($on);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfKind(Builder $query, RecurringKind $kind): Builder
    {
        return $query->where('kind', $kind->value);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('next_date')->orderBy('id');
    }
}
