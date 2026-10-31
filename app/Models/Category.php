<?php

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $name
 * @property CategoryType $type
 * @property int|null $default_fund_id
 * @property bool $tax_deductible_default
 * @property string $color
 * @property string|null $icon
 * @property bool $is_system
 */
#[Fillable([
    'name', 'type', 'default_fund_id', 'tax_deductible_default',
    'color', 'icon', 'is_system',
])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasTranslations;

    /** @var array<int, string> */
    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'tax_deductible_default' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /** @return BelongsTo<Fund, $this> */
    public function defaultFund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'default_fund_id');
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('type')->orderBy('id');
    }
}
