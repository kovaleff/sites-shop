<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimilarListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_similar_listings_are_ranked_by_category_and_price(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        $site = Site::factory()->paid()->create(['price' => 1_000_000]);
        $site->categories()->attach($category);

        $closest = Site::factory()->paid()->create(['price' => 1_050_000]);
        $closest->categories()->attach($category);

        $farther = Site::factory()->paid()->create(['price' => 1_250_000]);
        $farther->categories()->attach($category);

        $outOfBand = Site::factory()->paid()->create(['price' => 3_000_000]);
        $outOfBand->categories()->attach($category);

        $otherCategoryInBand = Site::factory()->paid()->create(['price' => 1_100_000]);
        $otherCategoryInBand->categories()->attach($otherCategory);

        $response = $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug().'/similar');

        $response->assertOk()->assertJsonCount(4, 'items');

        $this->assertSame(
            [$closest->id, $farther->id, $outOfBand->id, $otherCategoryInBand->id],
            array_column($response->json('items'), 'id'),
        );
    }

    public function test_similar_listings_exclude_the_current_listing(): void
    {
        $site = Site::factory()->paid()->create(['price' => 1_000_000]);
        Site::factory()->paid()->create(['price' => 1_000_000]);

        $response = $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug().'/similar');

        $response->assertOk()->assertJsonCount(1, 'items');
        $this->assertNotContains($site->id, array_column($response->json('items'), 'id'));
    }

    public function test_only_publicly_visible_listings_are_suggested(): void
    {
        $category = Category::factory()->create();

        $site = Site::factory()->paid()->create(['price' => 1_000_000]);
        $site->categories()->attach($category);

        $pending = Site::factory()->paid()->pendingModeration()->create(['price' => 1_000_000]);
        $pending->categories()->attach($category);

        Site::factory()->expired()->create(['price' => 1_000_000])->categories()->attach($category);
        Site::factory()->sold()->paid()->create(['price' => 1_000_000])->categories()->attach($category);

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug().'/similar')
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_similar_listings_have_empty_result_for_unrelated_listing(): void
    {
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        $site = Site::factory()->paid()->create(['price' => 100_000]);
        $site->categories()->attach($category);

        Site::factory()->paid()->create(['price' => 10_000_000])->categories()->attach($otherCategory);

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug().'/similar')
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_similar_listings_are_limited_to_six(): void
    {
        $category = Category::factory()->create();

        $site = Site::factory()->paid()->create(['price' => 1_000_000]);
        $site->categories()->attach($category);

        Site::factory()->count(8)->paid()->create(['price' => 1_100_000])
            ->each(fn (Site $candidate) => $candidate->categories()->attach($category));

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug().'/similar')
            ->assertOk()
            ->assertJsonCount(6, 'items');
    }

    public function test_similar_listings_mark_favorites_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $site = Site::factory()->paid()->create(['price' => 1_000_000]);
        $site->categories()->attach($category);

        $favorite = Site::factory()->paid()->create(['price' => 1_050_000]);
        $favorite->categories()->attach($category);
        $user->favoriteSites()->attach($favorite);

        $this->actingAs($user)
            ->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug().'/similar')
            ->assertOk()
            ->assertJsonPath('items.0.id', $favorite->id)
            ->assertJsonPath('items.0.isFavorite', true);
    }

    public function test_similar_listings_are_not_available_for_hidden_listing(): void
    {
        $site = Site::factory()->paid()->pendingModeration()->create();

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug().'/similar')->assertNotFound();
    }
}
