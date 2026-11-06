<?php

namespace Tests\Feature\Models;

use App\Models\SubscriptionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SubscriptionSettingCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_current_restores_model_from_cached_payload(): void
    {
        SubscriptionSetting::query()->updateOrCreate(['id' => 1], [
            'base_price' => 700,
            'periods' => [1, 3],
            'discounts' => [3 => 20],
            'target_status' => 'active',
            'qr_ttl_minutes' => 30,
            'is_active' => true,
        ]);
        Cache::forget(SubscriptionSetting::CACHE_KEY);

        $settings = SubscriptionSetting::current();

        $this->assertTrue($settings->exists);
        $this->assertSame('700.00', $settings->base_price);
        $this->assertSame([1, 3], $settings->normalizedPeriods());
        $this->assertSame(20, $settings->discountFor(3));
        $this->assertSame(30, $settings->qr_ttl_minutes);
        $this->assertTrue($settings->is_active);
    }

    public function test_current_caches_plain_array_instead_of_model(): void
    {
        SubscriptionSetting::current();

        $this->assertIsArray(Cache::get(SubscriptionSetting::CACHE_KEY));
    }

    public function test_current_recovers_when_cache_holds_unserializable_object(): void
    {
        SubscriptionSetting::query()->updateOrCreate(['id' => 1], [
            'base_price' => 500,
            'periods' => [1],
            'discounts' => [],
            'target_status' => 'active',
            'qr_ttl_minutes' => 15,
            'is_active' => true,
        ]);
        Cache::forget(SubscriptionSetting::CACHE_KEY);

        // Так Laravel отдаёт объект из сериализующего кеша при
        // cache.serializable_classes = false.
        Cache::put(
            SubscriptionSetting::CACHE_KEY,
            unserialize('O:8:"stdClass":0:{}', ['allowed_classes' => false]),
            SubscriptionSetting::CACHE_TTL_SECONDS,
        );

        $settings = SubscriptionSetting::current();

        $this->assertSame('500.00', $settings->base_price);
        $this->assertTrue($settings->is_active);
        $this->assertIsArray(Cache::get(SubscriptionSetting::CACHE_KEY));
    }

    public function test_current_falls_back_to_defaults_when_cached_payload_is_broken(): void
    {
        SubscriptionSetting::query()->delete();
        Cache::forget(SubscriptionSetting::CACHE_KEY);

        Cache::put(
            SubscriptionSetting::CACHE_KEY,
            unserialize('O:8:"stdClass":0:{}', ['allowed_classes' => false]),
            SubscriptionSetting::CACHE_TTL_SECONDS,
        );

        $settings = SubscriptionSetting::current();

        $this->assertFalse($settings->exists);
        $this->assertSame([1, 3, 6, 12], $settings->normalizedPeriods());
        $this->assertFalse($settings->is_active);
    }
}
