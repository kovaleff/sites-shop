<?php

namespace Tests\Feature\Orchid;

use App\Models\SubscriptionSetting;
use Illuminate\Support\Facades\Cache;

class SubscriptionSettingScreenTest extends OrchidTestCase
{
    public function test_screen_opens_for_authorized_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('platform.marketplace.subscription-settings'))
            ->assertOk()
            ->assertSee('Настройки подписки');
    }

    public function test_admin_can_save_tariff_settings(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('platform.marketplace.subscription-settings', ['method' => 'save']), [
            'settings' => [
                'base_price' => '750.50',
                'periods' => [1, 3, 6, 12],
                'discount_3' => 10,
                'discount_6' => 15,
                'discount_12' => 25,
                'target_status' => 'inactive',
                'qr_ttl_minutes' => 20,
                'is_active' => 1,
            ],
        ])->assertRedirect();

        $settings = SubscriptionSetting::query()->find(1);

        $this->assertNotNull($settings);
        $this->assertSame('750.50', $settings->base_price);
        $this->assertSame([1, 3, 6, 12], $settings->periods);
        $this->assertSame([3 => 10, 6 => 15, 12 => 25], $settings->normalizedDiscounts());
        $this->assertSame('inactive', $settings->target_status);
        $this->assertSame(20, $settings->qr_ttl_minutes);
        $this->assertTrue($settings->is_active);
        $this->assertNull(Cache::get(SubscriptionSetting::CACHE_KEY));
    }

    public function test_active_switch_submits_boolean_values(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('platform.marketplace.subscription-settings'))
            ->assertOk()
            // Скрытый input гарантирует, что выключенный переключатель
            // отправит 0, а не отсутствующее значение.
            ->assertSee('name="settings[is_active]" value="0"', false);
    }

    public function test_periods_without_one_month_are_rejected(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('platform.marketplace.subscription-settings', ['method' => 'save']), [
            'settings' => [
                'base_price' => '500',
                'periods' => [3, 6],
                'discount_3' => 10,
                'discount_6' => 15,
                'discount_12' => 0,
                'target_status' => 'active',
                'qr_ttl_minutes' => 15,
                'is_active' => 1,
            ],
        ])->assertSessionHasNoErrors();

        // Настройки не сохраняются: срок 1 месяц обязателен.
        $this->assertNull(SubscriptionSetting::query()->find(1));
    }

    public function test_discount_above_ninety_nine_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('platform.marketplace.subscription-settings', ['method' => 'save']), [
            'settings' => [
                'base_price' => '500',
                'periods' => [1, 3],
                'discount_3' => 150,
                'discount_6' => 0,
                'discount_12' => 0,
                'target_status' => 'active',
                'qr_ttl_minutes' => 15,
                'is_active' => 1,
            ],
        ])->assertSessionHasErrors('settings.discount_3');
    }

    public function test_tariff_can_be_disabled(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('platform.marketplace.subscription-settings', ['method' => 'save']), [
            'settings' => [
                'base_price' => '500',
                'periods' => [1],
                'discount_3' => 0,
                'discount_6' => 0,
                'discount_12' => 0,
                'target_status' => 'active',
                'qr_ttl_minutes' => 15,
                'is_active' => 0,
            ],
        ])->assertRedirect();

        $this->assertFalse(SubscriptionSetting::current()->is_active);
    }

    public function test_unauthorized_admin_cannot_open_settings(): void
    {
        $this->actingAs($this->admin(['platform.index' => true]));

        $this->get(route('platform.marketplace.subscription-settings'))
            ->assertForbidden();
    }
}
