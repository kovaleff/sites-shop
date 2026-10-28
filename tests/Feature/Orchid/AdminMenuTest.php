<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use App\Orchid\PlatformProvider;
use Illuminate\Support\Collection;
use Orchid\Screen\Actions\Menu;

class AdminMenuTest extends OrchidTestCase
{
    /**
     * @return array<string, string>
     */
    public static function marketplaceRoutes(): array
    {
        return [
            'Пользователи' => 'platform.systems.users',
            'Тематики' => 'platform.marketplace.categories',
            'Объявления' => 'platform.marketplace.sites',
            'Диалоги' => 'platform.marketplace.conversations',
            'Сообщения' => 'platform.marketplace.messages',
            'Платежи' => 'platform.marketplace.payments',
        ];
    }

    /**
     * @return Collection<int, Menu>
     */
    public static function marketplaceMenu(): Collection
    {
        $names = array_keys(self::marketplaceRoutes());

        return collect(app()->getProvider(PlatformProvider::class)->menu())
            ->filter(fn (Menu $menu) => in_array($menu->get('name'), $names, true))
            ->values();
    }

    public function test_marketplace_group_contains_exactly_six_links_in_order(): void
    {
        $this->actingAs($this->admin());

        $items = self::marketplaceMenu();

        $this->assertCount(6, $items);
        $this->assertSame(
            array_keys(self::marketplaceRoutes()),
            $items->map(fn (Menu $menu) => $menu->get('name'))->all(),
        );

        $this->assertSame('Биржа', $items->first()->get('title'));
    }

    public function test_marketplace_links_point_to_their_screens(): void
    {
        $this->actingAs($this->admin());

        foreach (self::marketplaceMenu() as $item) {
            $routeName = self::marketplaceRoutes()[$item->get('name')];

            $this->assertSame(route($routeName), $item->get('href'));
        }
    }

    public function test_preset_orchid_menu_items_are_not_registered(): void
    {
        $this->actingAs($this->admin());

        $names = collect(app()->getProvider(PlatformProvider::class)->menu())
            ->map(fn (Menu $menu) => $menu->get('name'))
            ->all();

        $this->assertSame(
            ['Пользователи', 'Тематики', 'Объявления', 'Диалоги', 'Сообщения', 'Платежи', 'Roles'],
            $names,
        );
    }

    public function test_each_marketplace_screen_opens_for_an_authorized_admin(): void
    {
        $this->actingAs($this->admin());

        foreach (self::marketplaceRoutes() as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }
}
