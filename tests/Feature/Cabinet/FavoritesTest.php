<?php

namespace Tests\Feature\Cabinet;

use App\Enums\ListingStatus;
use App\Models\Site;
use App\Models\User;
use App\Notifications\FavoriteSiteChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_use_favorites(): void
    {
        $site = Site::factory()->paid()->create();

        $this->getJson('/api/v1/favorites/ids')->assertUnauthorized();
        $this->getJson('/api/v1/favorites')->assertUnauthorized();
        $this->postJson('/api/v1/favorites/'.$site->id)->assertUnauthorized();
        $this->deleteJson('/api/v1/favorites/'.$site->id)->assertUnauthorized();
    }

    public function test_user_can_add_and_remove_a_favorite_idempotently(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->paid()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/favorites/'.$site->id)
            ->assertCreated()
            ->assertJsonPath('data.siteId', $site->id)
            ->assertJsonPath('data.isFavorite', true);

        $this->actingAs($user)
            ->postJson('/api/v1/favorites/'.$site->id)
            ->assertCreated();

        $this->assertDatabaseCount('favorites', 1);

        $this->actingAs($user)
            ->getJson('/api/v1/favorites/ids')
            ->assertOk()
            ->assertJsonPath('data.0', $site->id);

        $this->actingAs($user)
            ->deleteJson('/api/v1/favorites/'.$site->id)
            ->assertOk()
            ->assertJsonPath('data.isFavorite', false);

        $this->actingAs($user)
            ->deleteJson('/api/v1/favorites/'.$site->id)
            ->assertOk();

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_only_publicly_visible_listing_can_be_added(): void
    {
        $user = User::factory()->create();
        $hidden = Site::factory()->unpaid()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/favorites/'.$hidden->id)
            ->assertNotFound();

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_favorites_list_contains_sold_listings_with_status(): void
    {
        $user = User::factory()->create();
        $active = Site::factory()->paid()->create();
        $sold = Site::factory()->paid()->create();

        $user->favoriteSites()->attach($active);
        $user->favoriteSites()->attach($sold);

        $sold->changeStatus(ListingStatus::Sold);

        $response = $this->actingAs($user)->getJson('/api/v1/favorites');

        $response->assertOk()->assertJsonCount(2, 'data');

        $items = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($items[$active->id]['isAvailable']);
        $this->assertFalse($items[$sold->id]['isAvailable']);
        $this->assertSame('Продан', $items[$sold->id]['statusLabel']);
        $this->assertTrue($items[$active->id]['isFavorite']);
    }

    public function test_catalog_marks_favorites_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $favorite = Site::factory()->paid()->create();
        $other = Site::factory()->paid()->create();

        $user->favoriteSites()->attach($favorite);

        $items = collect(
            $this->actingAs($user)->getJson('/api/v1/catalog')->assertOk()->json('items')
        )->keyBy('id');

        $this->assertTrue($items[$favorite->id]['isFavorite']);
        $this->assertFalse($items[$other->id]['isFavorite']);
    }

    public function test_watchers_are_notified_when_favorite_price_changes(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        $watcher = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create(['price' => 100_000]);

        $watcher->favoriteSites()->attach($site);

        $site->update(['price' => 150_000]);

        Notification::assertSentTo($watcher, FavoriteSiteChanged::class, function (FavoriteSiteChanged $notification): bool {
            return $notification->change === 'price'
                && $notification->oldPrice === 100_000.0
                && (float) $notification->site->price === 150_000.0;
        });

        Notification::assertNotSentTo($seller, FavoriteSiteChanged::class);
    }

    public function test_watchers_are_notified_when_favorite_is_sold(): void
    {
        Notification::fake();

        $watcher = User::factory()->create();
        $site = Site::factory()->paid()->create();

        $watcher->favoriteSites()->attach($site);

        $site->changeStatus(ListingStatus::Sold);

        Notification::assertSentTo($watcher, FavoriteSiteChanged::class, function (FavoriteSiteChanged $notification): bool {
            return $notification->change === 'sold';
        });
    }

    public function test_disabled_notification_type_is_not_sent(): void
    {
        Notification::fake();

        $watcher = User::factory()->create();
        $site = Site::factory()->paid()->create();

        $watcher->favoriteSites()->attach($site);
        $watcher->updateNotificationSettings(['favorites' => false]);

        $site->update(['price' => 999_999]);

        Notification::assertNothingSentTo($watcher);
    }

    public function test_price_change_of_unpublished_listing_is_not_notified(): void
    {
        Notification::fake();

        $watcher = User::factory()->create();
        $site = Site::factory()->unpaid()->create(['price' => 100_000]);

        $watcher->favoriteSites()->attach($site);

        $site->update(['price' => 200_000]);

        Notification::assertNothingSentTo($watcher);
    }
}
