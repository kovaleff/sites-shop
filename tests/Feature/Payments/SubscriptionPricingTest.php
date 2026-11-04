<?php

namespace Tests\Feature\Payments;

use App\Exceptions\PaymentException;
use App\Models\SubscriptionSetting;
use App\Services\Payments\SubscriptionPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SubscriptionPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_one_month_has_no_discount(): void
    {
        $this->settings(['base_price' => 500]);

        $quote = app(SubscriptionPricing::class)->quote(1);

        $this->assertSame(500, $quote['total']);
        $this->assertSame(0, $quote['discountPercent']);
        $this->assertSame(0.0, (float) $quote['discountAmount']);
        $this->assertSame(500.0, (float) $quote['perMonth']);
    }

    public function test_three_six_and_twelve_month_discounts_are_applied(): void
    {
        $this->settings(['base_price' => 500]);

        $pricing = app(SubscriptionPricing::class);

        $this->assertSame(1350, $pricing->quote(3)['total']);   // 1500 − 10%
        $this->assertSame(2550, $pricing->quote(6)['total']);   // 3000 − 15%
        $this->assertSame(4500, $pricing->quote(12)['total']);  // 6000 − 25%
    }

    public function test_discount_amount_matches_total(): void
    {
        $this->settings(['base_price' => 500]);

        $quote = app(SubscriptionPricing::class)->quote(3);

        $this->assertSame(1500.0, (float) $quote['baseAmount']);
        $this->assertSame(150.0, (float) $quote['discountAmount']);
        $this->assertSame(
            (int) round($quote['baseAmount'] - $quote['discountAmount']),
            $quote['total'],
        );
    }

    public function test_half_ruble_rounds_up(): void
    {
        $this->settings([
            'base_price' => 0.50,
            'periods' => [1, 3],
            'discounts' => [3 => 50],
        ]);

        // 0.50 × 3 = 1.50; минус 50% = 0.75 → округление вверх до 1.
        $this->assertSame(1, app(SubscriptionPricing::class)->quote(3)['total']);
    }

    public function test_kopecks_in_base_price_round_to_whole_rubles(): void
    {
        $this->settings([
            'base_price' => 333.33,
            'periods' => [3],
            'discounts' => [],
        ]);

        // 333.33 × 3 = 999.99 → 1000.
        $this->assertSame(1000, app(SubscriptionPricing::class)->quote(3)['total']);
    }

    public function test_period_outside_configured_set_is_rejected(): void
    {
        $this->settings(['base_price' => 500, 'periods' => [1, 3]]);

        $this->expectException(PaymentException::class);

        app(SubscriptionPricing::class)->quote(6);
    }

    public function test_zero_total_is_rejected(): void
    {
        $this->settings(['base_price' => 0, 'periods' => [1]]);

        $this->expectException(PaymentException::class);

        app(SubscriptionPricing::class)->quote(1);
    }

    public function test_inactive_tariff_blocks_new_invoices(): void
    {
        $this->settings(['base_price' => 500, 'is_active' => false]);

        $this->expectException(PaymentException::class);

        app(SubscriptionPricing::class)->assertTariffActive();
    }

    public function test_periods_returns_all_quotes_in_configured_order(): void
    {
        $this->settings(['base_price' => 500]);

        $periods = app(SubscriptionPricing::class)->periods();

        $this->assertSame([1, 3, 6, 12], array_column($periods, 'months'));
        $this->assertSame([500, 1350, 2550, 4500], array_column($periods, 'total'));
    }

    public function test_settings_fall_back_to_defaults_when_row_is_missing(): void
    {
        SubscriptionSetting::query()->delete();
        Cache::forget(SubscriptionSetting::CACHE_KEY);

        $settings = SubscriptionSetting::current();

        $this->assertSame([1, 3, 6, 12], $settings->normalizedPeriods());
        $this->assertFalse($settings->is_active);
        $this->assertSame(0.0, (float) $settings->base_price);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function settings(array $attributes): SubscriptionSetting
    {
        $settings = SubscriptionSetting::query()->updateOrCreate(
            ['id' => 1],
            array_merge([
                'base_price' => 500,
                'periods' => [1, 3, 6, 12],
                'discounts' => [3 => 10, 6 => 15, 12 => 25],
                'target_status' => 'active',
                'qr_ttl_minutes' => 15,
                'is_active' => true,
            ], $attributes),
        );

        Cache::forget(SubscriptionSetting::CACHE_KEY);

        return $settings;
    }
}
