<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word());

        return [
            'name' => ['ms' => $name, 'en' => $name],
            'type' => CategoryType::Expense,
            'default_fund_id' => null,
            'tax_deductible_default' => false,
            'color' => '#71717a',
            'icon' => null,
            'is_system' => false,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => CategoryType::Income]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }
}
