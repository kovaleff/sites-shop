<?php

namespace Database\Factories;

use App\Enums\PaymentReviewDecision;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPaymentReview>
 */
class SubscriptionPaymentReviewFactory extends Factory
{
    protected $model = SubscriptionPaymentReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_payment_id' => SubscriptionPayment::factory()->requiresReview(),
            'admin_id' => User::factory(),
            'decision' => PaymentReviewDecision::Accepted,
            'comment' => fake()->sentence(8),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'decision' => PaymentReviewDecision::Accepted,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'decision' => PaymentReviewDecision::Rejected,
        ]);
    }
}
