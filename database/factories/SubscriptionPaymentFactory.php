<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
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
            'site_domain' => fn (array $attributes) => Site::query()
                ->findOrFail($attributes['site_id'])
                ->domain,
            'amount' => 500,
            'base_price' => 500,
            'base_amount' => 500,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'currency' => 'RUB',
            'period_months' => 1,
            'target_status' => ListingStatus::Active->value,
            'receipt_email' => fake()->safeEmail(),
            'receipt_payload' => [
                'sno' => 'osn',
                'items' => [],
            ],
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
            'qr_expires_at' => now()->addMinutes(15),
            'external_id' => (string) Str::uuid(),
            'provider' => PaymentProvider::Robokassa->value,
            'payment_method' => PaymentMethod::Sbp->value,
            'provider_payload' => null,
            'failure_reason' => null,
            'review_reason' => null,
            'is_test' => false,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => 'provider_error',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Cancelled,
            'failure_reason' => 'expired',
        ]);
    }

    public function requiresReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::RequiresReview,
            'paid_at' => now(),
            'review_reason' => 'amount_mismatch',
        ]);
    }

    public function test(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_test' => true,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'qr_expires_at' => now()->subMinute(),
        ]);
    }
}
