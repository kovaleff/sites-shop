<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'domain' => fake()->unique()->domainName(),
            'description' => fake()->realText(220),
            'price' => fake()->numberBetween(10_000, 5_000_000),
            'monthly_revenue' => fake()->numberBetween(0, 500_000),
            'has_trademark' => fake()->boolean(),
            'status' => ListingStatus::Active,
            'paid_until' => null,
            'sold_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Inactive,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Withdrawn,
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Sold,
            'sold_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_until' => today()->addMonthNoOverflow(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_until' => today()->subDay(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_until' => null,
        ]);
    }

    public function withTrademark(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_trademark' => true,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified' => true,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'hidden_at' => now(),
        ]);
    }

    public function needsRenewal(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_until' => today()->addDays(3),
        ]);
    }

    public function zone(string $zone): static
    {
        return $this->state(fn (array $attributes) => [
            'domain' => fake()->unique()->domainWord().'.'.$zone,
        ]);
    }
}
