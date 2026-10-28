<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'user_id' => fn (array $attributes) => Site::query()
                ->findOrFail($attributes['site_id'])
                ->user_id,
            'amount' => fake()->randomFloat(2, 100, 10_000),
            'currency' => 'RUB',
            'period_months' => 1,
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
            'external_id' => (string) Str::uuid(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Cancelled,
        ]);
    }
}
