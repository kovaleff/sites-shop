<?php

namespace Tests\Feature\Cabinet;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/my/sites')->assertUnauthorized();
    }

    public function test_returns_only_sites_of_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $own = Site::factory()->for($owner, 'owner')->create();
        Site::factory()->for($other, 'owner')->create();

        $response = $this->actingAs($owner)->getJson('/api/v1/my/sites');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_sites_are_sorted_newest_first(): void
    {
        $owner = User::factory()->create();

        $older = Site::factory()->for($owner, 'owner')->create(['created_at' => now()->subDays(2)]);
        $newer = Site::factory()->for($owner, 'owner')->create(['created_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_list_is_paginated_by_25(): void
    {
        $owner = User::factory()->create();
        Site::factory()->count(30)->for($owner, 'owner')->create();

        $response = $this->actingAs($owner)->getJson('/api/v1/my/sites');

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.lastPage', 2)
            ->assertJsonPath('meta.perPage', 25);
    }

    public function test_hidden_filter_returns_only_hidden_sold_sites(): void
    {
        $owner = User::factory()->create();

        $visible = Site::factory()->for($owner, 'owner')->create();
        $hidden = Site::factory()->for($owner, 'owner')->sold()->hidden()->create();

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites?hidden=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hidden->id);
    }

    public function test_unread_messages_are_counted_per_site(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $otherSite = Site::factory()->for($seller, 'owner')->create();

        $conversation = Conversation::factory()->forSite($site, $buyer)->create();
        Message::factory()->for($conversation)->create(['body' => 'Первое']);
        Message::factory()->for($conversation)->create(['body' => 'Второе']);
        Message::factory()->for($conversation)->read()->create(['body' => 'Прочитано']);

        $response = $this->actingAs($seller)->getJson('/api/v1/my/sites');

        $response->assertOk();

        $items = collect($response->json('data'))->keyBy('id');

        $this->assertSame(2, $items[$site->id]['unreadMessages']);
        $this->assertSame(0, $items[$otherSite->id]['unreadMessages']);
    }

    public function test_categories_are_returned_with_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();
        $category = Category::factory()->create(['name' => 'Компьютеры и IT']);
        $site->categories()->attach($category);

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonPath('data.0.categories.0.name', 'Компьютеры и IT')
            ->assertJsonPath('data.0.needsRenewal', true)
            ->assertJsonPath('data.0.isPubliclyVisible', false);
    }

    public function test_foreign_site_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $site = Site::factory()->for($other, 'owner')->create();

        $this->actingAs($owner)
            ->getJson("/api/v1/my/sites/{$site->id}")
            ->assertNotFound();
    }
}
