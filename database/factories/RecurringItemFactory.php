<?php

namespace Database\Factories;

use App\Enums\RecurringInterval;
use App\Enums\RecurringKind;
use App\Models\Client;
use App\Models\RecurringItem;
use App\Services\RecurringScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringItem>
 */
class RecurringItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $next = CarbonImmutable::today()->addDays(fake()->numberBetween(1, 90));

        return [
            'kind' => RecurringKind::OwnCost,
            'client_id' => null,
            'project_id' => null,
            'category_id' => null,
            'account_id' => null,
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'amount' => fake()->numberBetween(20_00, 2_000_00),
            'interval' => RecurringInterval::Monthly,
            'next_date' => $next->toDateString(),
            'anchor_day' => $next->day,
            'auto_generate' => false,
            'remind_days_before' => 14,
            'last_reminded_at' => null,
            'is_active' => true,
        ];
    }

    public function clientBilling(?Client $client = null): static
    {
        return $this->state(fn () => [
            'kind' => RecurringKind::ClientBilling,
            'client_id' => $client === null ? Client::factory() : $client->id,
        ]);
    }

    public function ownCost(): static
    {
        return $this->state(fn () => [
            'kind' => RecurringKind::OwnCost,
            'client_id' => null,
        ]);
    }

    public function autoGenerating(): static
    {
        return $this->state(fn () => ['auto_generate' => true]);
    }

    public function every(RecurringInterval $interval): static
    {
        return $this->state(fn () => ['interval' => $interval]);
    }

    /** The anchor day follows the date, as the form makes it. */
    public function dueOn(string $date): static
    {
        return $this->state(fn () => [
            'next_date' => $date,
            'anchor_day' => RecurringScheduleService::anchorFor($date),
        ]);
    }

    public function amount(int $sen): static
    {
        return $this->state(fn () => ['amount' => $sen]);
    }

    public function remindingDaysBefore(int $days): static
    {
        return $this->state(fn () => ['remind_days_before' => $days]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
