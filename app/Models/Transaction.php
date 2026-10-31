<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property TransactionType $type
 * @property int $account_id
 * @property int|null $to_account_id
 * @property int $amount Always positive; the type decides the direction.
 * @property CarbonImmutable $date
 * @property string $description
 * @property string|null $reference
 * @property int|null $category_id
 * @property int|null $fund_id
 * @property int|null $client_id
 * @property int|null $project_id
 * @property int|null $invoice_id
 * @property int|null $recurring_item_id
 * @property bool $tax_deductible
 * @property string|null $receipt_path
 */
#[Fillable([
    'type', 'account_id', 'to_account_id', 'amount', 'date', 'description',
    'reference', 'category_id', 'fund_id', 'client_id', 'project_id',
    'invoice_id', 'recurring_item_id', 'tax_deductible', 'receipt_path',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * The fields worth an audit line: the money, when it happened, and what
     * it was filed under.
     *
     * @return array<int, string>
     */
    public function auditedAttributes(): array
    {
        return [
            'type', 'amount', 'date', 'description', 'account_id',
            'to_account_id', 'category_id', 'fund_id', 'invoice_id',
            'client_id', 'project_id', 'tax_deductible', 'reference',
        ];
    }

    public function auditSubject(): string
    {
        return $this->description;
    }

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'integer',
            'date' => 'date',
            'tax_deductible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Fund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
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

    /** The invoice this payment settles, for an income recorded from one. */
    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** The recurring own cost this expense settles, if any. */
    /** @return BelongsTo<RecurringItem, $this> */
    public function recurringItem(): BelongsTo
    {
        return $this->belongsTo(RecurringItem::class);
    }

    /** @return HasMany<FundMovement, $this> */
    public function fundMovements(): HasMany
    {
        return $this->hasMany(FundMovement::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from !== null, fn (Builder $q) => $q->whereDate('date', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->whereDate('date', '<=', $to));
    }
}
