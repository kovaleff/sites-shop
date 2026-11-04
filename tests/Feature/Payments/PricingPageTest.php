<?php

namespace Tests\Feature\Payments;

use App\Models\SubscriptionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_public_pricing_matches_cabinet_settings(): void
    {
        $this->settings(basePrice: 500, isActive: true);

        $this->getJson(route('api.v1.pricing'))
            ->assertOk()
            ->assertJsonPath('basePrice', 500)
            ->assertJsonPath('isActive', true)
            ->assertJsonPath('periods.0.months', 1)
            ->assertJsonPath('periods.0.total', 500)
            ->assertJsonPath('periods.1.months', 3)
            ->assertJsonPath('periods.1.discountPercent', 10)
            ->assertJsonPath('periods.1.total', 1350)
            ->assertJsonPath('periods.2.total', 2550)
            ->assertJsonPath('periods.3.total', 4500);
    }

    public function test_inactive_tariff_is_reported_as_unavailable(): void
    {
        $this->settings(basePrice: 500, isActive: false);

        $this->getJson(route('api.v1.pricing'))
            ->assertOk()
            ->assertJsonPath('isActive', false);
    }

    public function test_pricing_is_publicly_accessible_without_authentication(): void
    {
        $this->settings(basePrice: 700, isActive: true);

        $this->getJson(route('api.v1.pricing'))->assertOk();
    }

    private function settings(float $basePrice, bool $isActive): void
    {
        SubscriptionSetting::query()->updateOrCreate(['id' => 1], [
            'base_price' => $basePrice,
            'periods' => [1, 3, 6, 12],
            'discounts' => [3 => 10, 6 => 15, 12 => 25],
            'target_status' => 'active',
            'qr_ttl_minutes' => 15,
            'is_active' => $isActive,
        ]);

        Cache::forget(SubscriptionSetting::CACHE_KEY);
    }
}
