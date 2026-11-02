<?php

namespace Tests\Feature\Catalog;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_profile_exposes_stats_and_published_listings(): void
    {
        $seller = User::factory()->create([
            'name' => 'Иван Петров',
            'bio' => 'Продаю сайты с 2015 года.',
            'city' => 'Москва',
        ]);

        $published = Site::factory()->paid()->verified()->create([
            'user_id' => $seller->id,
            'title' => 'Магазин товаров для дома',
        ]);
        Site::factory()->paid()->create(['user_id' => $seller->id]);
        Site::factory()->sold()->create(['user_id' => $seller->id]);
        Site::factory()->unpaid()->create(['user_id' => $seller->id]);

        $response = $this->getJson('/api/v1/sellers/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.id', $seller->id)
            ->assertJsonPath('data.displayName', 'Иван Петров')
            ->assertJsonPath('data.bio', 'Продаю сайты с 2015 года.')
            ->assertJsonPath('data.city', 'Москва')
            ->assertJsonPath('data.isVerifiedSeller', true)
            ->assertJsonPath('data.publishedSitesCount', 2)
            ->assertJsonPath('data.soldSitesCount', 1)
            ->assertJsonPath('data.profileUrl', '/seller/'.$seller->id)
            ->assertJsonCount(2, 'listings')
            ->assertJsonPath('listings.0.seller.id', $seller->id)
            ->assertJsonPath('listings.0.seller.displayName', 'Иван Петров')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.page', 1);

        $this->assertSame($published->id, $response->json('listings.1.id'));
    }

    public function test_seller_without_verified_domains_is_not_marked_verified(): void
    {
        $seller = User::factory()->create();
        Site::factory()->paid()->create(['user_id' => $seller->id]);

        $this->getJson('/api/v1/sellers/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.isVerifiedSeller', false)
            ->assertJsonPath('data.publishedSitesCount', 1)
            ->assertJsonPath('data.soldSitesCount', 0);
    }

    public function test_seller_without_bio_and_listings_is_available(): void
    {
        $seller = User::factory()->create(['bio' => null, 'city' => null]);

        $this->getJson('/api/v1/sellers/'.$seller->id)
            ->assertOk()
            ->assertJsonPath('data.bio', null)
            ->assertJsonPath('data.city', null)
            ->assertJsonCount(0, 'listings')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_unknown_seller_returns_not_found(): void
    {
        $this->getJson('/api/v1/sellers/999999')->assertNotFound();
    }
}
