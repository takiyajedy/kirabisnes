<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issued = fake()->dateTimeBetween('-3 months');
        $total = fake()->numberBetween(500_00, 20_000_00);

        return [
            'client_id' => Client::factory(),
            'project_id' => null,
            'quotation_id' => null,
            'number' => 'INV-'.fake()->unique()->numberBetween(100_000, 999_999),
            'issue_date' => $issued->format('Y-m-d'),
            'due_date' => (clone $issued)->modify('+14 days')->format('Y-m-d'),
            'status' => InvoiceStatus::Draft,
            'locale' => Locale::Malay,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'paid_amount' => 0,
            'notes' => null,
            'terms' => null,
            'sent_at' => null,
            'paid_at' => null,
        ];
    }

    public function withStatus(InvoiceStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'sent_at' => $status === InvoiceStatus::Draft ? null : now(),
        ]);
    }

    public function totalling(int $sen): static
    {
        return $this->state(fn () => ['subtotal' => $sen, 'total' => $sen]);
    }

    public function dueOn(string $date): static
    {
        return $this->state(fn () => ['due_date' => $date]);
    }
}
