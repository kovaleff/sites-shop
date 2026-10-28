<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use App\Models\Conversation;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;

class UserMarketplaceTest extends OrchidTestCase
{
    public function test_user_card_shows_related_sites_conversations_and_payments(): void
    {
        $this->actingAs($this->admin());

        $user = User::factory()->create();
        $ownedSite = Site::factory()->for($user, 'owner')->create(['domain' => 'owned-by-user.test']);

        Conversation::factory()
            ->forSite($ownedSite, User::factory()->create(['name' => 'Buyer Person']))
            ->create();

        Conversation::factory()
            ->forSite(Site::factory()->create(), $user)
            ->create();

        SubscriptionPayment::factory()->for($ownedSite)->create([
            'amount' => 1234.56,
        ]);

        $this->get(route('platform.systems.users.edit', $user))
            ->assertOk()
            ->assertSee('owned-by-user.test')
            ->assertSee('Buyer Person')
            ->assertSee('1 234.56');
    }

    public function test_user_card_shows_only_the_latest_ten_records(): void
    {
        $this->actingAs($this->admin());

        $user = User::factory()->create();

        collect(range(1, 11))->each(
            fn (int $index) => Site::factory()->for($user, 'owner')->create([
                'domain' => "user-site-{$index}.test",
                'created_at' => now()->subDays(11 - $index),
            ])
        );

        $this->get(route('platform.systems.users.edit', $user))
            ->assertOk()
            ->assertSee('user-site-11.test')
            ->assertDontSee('user-site-1.test');
    }

    public function test_user_list_shows_marketplace_counters(): void
    {
        $this->actingAs($this->admin());

        $user = User::factory()->create(['name' => 'Marketplace Owner']);
        $sites = Site::factory()->count(2)->for($user, 'owner')->create();

        SubscriptionPayment::factory()->count(3)->for($sites->first())->create();

        collect(range(1, 4))->each(
            fn () => Conversation::factory()
                ->forSite(Site::factory()->create(), $user)
                ->create()
        );

        $this->get(route('platform.systems.users'))
            ->assertOk()
            ->assertSee('Marketplace Owner')
            ->assertSee('Объявлений')
            ->assertSee('Диалогов')
            ->assertSee('Платежей');
    }

    public function test_user_deletion_is_not_available(): void
    {
        $this->actingAs($this->admin());

        $user = User::factory()->create();

        $this->get(route('platform.systems.users.edit', $user))
            ->assertOk()
            ->assertDontSee('bs.trash3');

        $this->get(route('platform.systems.users'))
            ->assertOk()
            ->assertDontSee('bs.trash3');

        // Orchid's admin fallback route only allows GET, so POSTing to a
        // non-existent screen action is rejected with 405 instead of 404.
        $this->post(route('platform.systems.users.edit', [$user, 'remove']))
            ->assertStatus(405);

        $this->post(route('platform.systems.users', ['method' => 'remove']))
            ->assertStatus(405);

        $this->assertFalse(method_exists(UserEditScreen::class, 'remove'));
        $this->assertFalse(method_exists(UserListScreen::class, 'remove'));

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
