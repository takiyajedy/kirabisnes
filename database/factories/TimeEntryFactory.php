<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $started = CarbonImmutable::parse(fake()->dateTimeBetween('-2 months'));
        $minutes = fake()->numberBetween(15, 480);

        return [
            'project_id' => Project::factory(),
            'started_at' => $started,
            'ended_at' => $started->addMinutes($minutes),
            'minutes' => $minutes,
            'note' => null,
            'billable' => true,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => ['ended_at' => null, 'minutes' => null]);
    }

    public function lasting(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'minutes' => $minutes,
            'ended_at' => CarbonImmutable::parse($attributes['started_at'])
                ->addMinutes($minutes),
        ]);
    }

    public function unbillable(): static
    {
        return $this->state(fn () => ['billable' => false]);
    }

    public function startingAt(string $at): static
    {
        return $this->state(fn () => ['started_at' => $at]);
    }
}
