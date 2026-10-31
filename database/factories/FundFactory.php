<?php

namespace Database\Factories;

use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Fund>
 */
class FundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ['ms' => Str::title($name), 'en' => Str::title($name)],
            'slug' => Str::slug($name, '_'),
            'percentage' => 0,
            'color' => '#059669',
            'sort_order' => 0,
            'is_active' => true,
            'is_system' => false,
        ];
    }
}
