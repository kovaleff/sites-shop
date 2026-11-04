<?php

namespace Database\Factories;

use App\Models\SubscriptionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionSetting>
 */
class SubscriptionSettingFactory extends Factory
{
    protected $model = SubscriptionSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_price' => 500.00,
            'periods' => [1, 3, 6, 12],
            'discounts' => [3 => 10, 6 => 15, 12 => 25],
            'target_status' => 'active',
            'qr_ttl_minutes' => 15,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function withoutDiscounts(): static
    {
        return $this->state(fn (array $attributes): array => [
            'discounts' => [],
        ]);
    }

    public function targetInactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'target_status' => 'inactive',
        ]);
    }

    public function shortQrTtl(): static
    {
        return $this->state(fn (array $attributes): array => [
            'qr_ttl_minutes' => 1,
        ]);
    }
}
