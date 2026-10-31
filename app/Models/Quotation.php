<?php

namespace App\Models;

use App\Enums\Locale;
use App\Enums\QuotationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\QuotationFactory;
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
 * @property string $number
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $valid_until
 * @property QuotationStatus $status
 * @property Locale $locale
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property string|null $notes
 * @property string|null $terms
 * @property int|null $converted_invoice_id
 */
#[Fillable([
    'client_id', 'project_id', 'number', 'issue_date', 'valid_until', 'status',
    'locale', 'subtotal', 'discount', 'total', 'notes', 'terms',
    'converted_invoice_id',
])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'locale' => Locale::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
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

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_invoice_id !== null;
    }

    /**
     * Has the validity date passed without an answer?
     */
    public function hasLapsed(): bool
    {
        return $this->status === QuotationStatus::Sent
            && $this->valid_until->isPast();
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
