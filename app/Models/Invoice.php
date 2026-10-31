<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $quotation_id
 * @property int|null $recurring_item_id
 * @property string $number
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $due_date
 * @property InvoiceStatus $status
 * @property Locale $locale
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property int $paid_amount
 * @property string|null $notes
 * @property string|null $terms
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $paid_at
 */
#[Fillable([
    'client_id', 'project_id', 'quotation_id', 'recurring_item_id', 'number',
    'issue_date', 'due_date', 'status', 'locale', 'subtotal', 'discount',
    'total', 'paid_amount', 'notes', 'terms', 'sent_at', 'paid_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @return array<int, string>
     */
    public function auditedAttributes(): array
    {
        return [
            'status', 'total', 'subtotal', 'discount', 'paid_amount',
            'issue_date', 'due_date', 'client_id', 'project_id', 'locale',
            'sent_at', 'paid_at',
        ];
    }

    public function auditSubject(): string
    {
        return $this->number;
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'locale' => Locale::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid_amount' => 'integer',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
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

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** The recurring item that drafted this invoice, if one did. */
    /** @return BelongsTo<RecurringItem, $this> */
    public function recurringItem(): BelongsTo
    {
        return $this->belongsTo(RecurringItem::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The payments recorded against this invoice. */
    /** @return HasMany<Transaction, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('date')->orderBy('id');
    }

    /**
     * What is still owed, never below zero.
     */
    public function outstanding(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }

    /**
     * Has the due date gone by?
     *
     * The due date is a date, not a moment: an invoice due today is due by
     * the end of today, so it only falls overdue tomorrow. Comparing against
     * `now()` would call it late from one minute past midnight.
     */
    public function isPastDue(): bool
    {
        return $this->due_date->lessThan(CarbonImmutable::today());
    }

    /**
     * Is this invoice past its due date with money still owed?
     */
    public function isLate(): bool
    {
        return $this->status->isOutstanding() && $this->isPastDue();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Sent->value,
            InvoiceStatus::Partial->value,
            InvoiceStatus::Overdue->value,
        ]);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('issue_date')->orderByDesc('id');
    }
}
