<?php

namespace Database\Factories;

use App\Enums\Locale;
use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issued = fake()->dateTimeBetween('-3 months');

        return [
            'client_id' => Client::factory(),
            'project_id' => null,
            'number' => 'QUO-'.fake()->unique()->numberBetween(100_000, 999_999),
            'issue_date' => $issued->format('Y-m-d'),
            'valid_until' => (clone $issued)->modify('+30 days')->format('Y-m-d'),
            'status' => QuotationStatus::Draft,
            'locale' => Locale::Malay,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'notes' => null,
            'terms' => null,
        ];
    }

    public function withStatus(QuotationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
