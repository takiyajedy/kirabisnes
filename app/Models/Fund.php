<?php

namespace App\Models;

use Database\Factories\FundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $percentage
 * @property string $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['name', 'slug', 'percentage', 'color', 'sort_order', 'is_active', 'is_system'])]
class Fund extends Model
{
    /** @use HasFactory<FundFactory> */
    use HasFactory, HasTranslations;

    /** Computed by FundBalanceService; never stored. */
    public ?int $balance = null;

    /** @var array<int, string> */
    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /** @return HasMany<FundMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(FundMovement::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'default_fund_id');
    }

    /**
     * The share of every income this fund receives, in basis points
     * (30.00% => 3000). Integer maths only — see PLAN.md section 3.1.
     */
    public function percentageInBasisPoints(): int
    {
        return (int) round(((float) $this->percentage) * 100);
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
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
