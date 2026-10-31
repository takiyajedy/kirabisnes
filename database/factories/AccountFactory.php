<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->word().' Account'),
            'type' => AccountType::Bank,
            'bank_name' => 'Maybank',
            'opening_balance' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::Cash,
            'bank_name' => null,
        ]);
    }

    public function withOpeningBalance(int $sen): static
    {
        return $this->state(fn () => ['opening_balance' => $sen]);
    }
}
