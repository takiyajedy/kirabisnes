<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'reg_no' => null,
            'notes' => null,
            'preferred_locale' => Locale::Malay,
        ];
    }

    public function speaking(Locale $locale): static
    {
        return $this->state(fn () => ['preferred_locale' => $locale]);
    }
}
