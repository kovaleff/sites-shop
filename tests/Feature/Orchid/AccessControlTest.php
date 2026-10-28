<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use Orchid\Screen\Actions\Menu;

class AccessControlTest extends OrchidTestCase
{
    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('platform.marketplace.sites'))
            ->assertRedirect(route('platform.login'));
    }

    public function test_user_without_permission_gets_forbidden(): void
    {
        $this->actingAs($this->admin(['platform.index' => true]));

        $this->get(route('platform.marketplace.sites'))
            ->assertForbidden();
    }

    public function test_menu_hides_links_without_permission(): void
    {
        $this->actingAs($this->admin(['platform.index' => true]));

        $visible = AdminMenuTest::marketplaceMenu()
            ->filter(fn (Menu $menu) => $menu->isSee())
            ->map(fn (Menu $menu) => $menu->get('name'))
            ->all();

        $this->assertSame([], $visible);
    }

    public function test_user_with_a_single_permission_sees_only_that_marketplace_link(): void
    {
        $this->actingAs($this->admin(['platform.marketplace.sites' => true]));

        $visible = AdminMenuTest::marketplaceMenu()
            ->filter(fn (Menu $menu) => $menu->isSee())
            ->map(fn (Menu $menu) => $menu->get('name'))
            ->values()
            ->all();

        $this->assertSame(['Объявления'], $visible);

        $this->get(route('platform.marketplace.sites'))->assertOk();
        $this->get(route('platform.marketplace.categories'))->assertForbidden();
    }
}
