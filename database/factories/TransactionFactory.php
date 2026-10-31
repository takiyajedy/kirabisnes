<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Fund;
use App\Models\Transaction;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TransactionType::Income,
            'account_id' => Account::factory(),
            'to_account_id' => null,
            'amount' => fake()->numberBetween(1_00, 5_000_00),
            'date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'description' => fake()->sentence(3),
            'reference' => null,
            'category_id' => null,
            'fund_id' => null,
            'tax_deductible' => false,
            'receipt_path' => null,
        ];
    }

    public function ofType(TransactionType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function amount(int $sen): static
    {
        return $this->state(fn () => ['amount' => $sen]);
    }

    public function on(DateTimeInterface|string $date): static
    {
        return $this->state(fn () => [
            'date' => $date instanceof DateTimeInterface
                ? $date->format('Y-m-d')
                : $date,
        ]);
    }

    public function inAccount(Account $account): static
    {
        return $this->state(fn () => ['account_id' => $account->id]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }

    public function fromFund(Fund $fund): static
    {
        return $this->state(fn () => ['fund_id' => $fund->id]);
    }
}
