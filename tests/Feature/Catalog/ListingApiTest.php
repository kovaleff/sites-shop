<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_listing_is_available_by_slug(): void
    {
        $site = Site::factory()->paid()->create(['title' => 'Каталог мебели']);
        $category = Category::factory()->create(['name' => 'Мебель, интерьер и быт']);
        $site->categories()->attach($category);

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug())
            ->assertOk()
            ->assertJsonPath('data.id', $site->id)
            ->assertJsonPath('data.slug', $site->id.'-'.$site->slug())
            ->assertJsonPath('data.url', $site->canonicalPath())
            ->assertJsonPath('data.title', 'Каталог мебели')
            ->assertJsonPath('data.categories.0.name', 'Мебель, интерьер и быт');
    }

    public function test_listing_exposes_payback_months(): void
    {
        $site = Site::factory()->paid()->create([
            'price' => 1_200_000,
            'monthly_revenue' => 100_000,
        ]);

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug())
            ->assertOk()
            ->assertJsonPath('data.paybackMonths', 12);
    }

    public function test_listing_without_revenue_has_no_payback(): void
    {
        $site = Site::factory()->paid()->create(['monthly_revenue' => 0]);

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug())
            ->assertOk()
            ->assertJsonPath('data.paybackMonths', null);
    }

    public function test_hidden_listing_returns_not_found(): void
    {
        $site = Site::factory()->paid()->pendingModeration()->create();

        $this->getJson('/api/v1/listings/'.$site->id.'-'.$site->slug())->assertNotFound();
    }

    public function test_listing_by_id_only_is_supported(): void
    {
        $site = Site::factory()->paid()->create();

        $this->getJson('/api/v1/listings/'.$site->id)
            ->assertOk()
            ->assertJsonPath('data.id', $site->id);
    }
}
