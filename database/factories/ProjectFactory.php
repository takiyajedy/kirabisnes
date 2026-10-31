<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => null,
            'contract_amount' => fake()->numberBetween(1_000_00, 50_000_00),
            'status' => ProjectStatus::Active,
            'start_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'end_date' => null,
            'internal_hourly_rate' => null,
        ];
    }

    public function withStatus(ProjectStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
