<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_publicly_visible_sites_are_returned(): void
    {
        $visible = Site::factory()->paid()->create();
        Site::factory()->unpaid()->create();
        Site::factory()->expired()->create();
        Site::factory()->inactive()->paid()->create();
        Site::factory()->withdrawn()->paid()->create();
        Site::factory()->sold()->paid()->create();

        $response = $this->getJson('/api/v1/catalog');

        $response->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $visible->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.perPage', 25);
    }

    public function test_price_range_is_applied_and_upper_bound_is_open(): void
    {
        $cheap = Site::factory()->paid()->create(['price' => 1000]);
        $expensive = Site::factory()->paid()->create(['price' => 20_000_000]);

        $this->getJson('/api/v1/catalog?price_min=500')
            ->assertOk()
            ->assertJsonCount(2, 'items');

        $this->getJson('/api/v1/catalog?price_min=500&price_max=5000')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $cheap->id);

        $this->assertNotNull($expensive);
    }

    public function test_income_range_is_applied(): void
    {
        Site::factory()->paid()->create(['monthly_revenue' => 10_000]);
        $rich = Site::factory()->paid()->create(['monthly_revenue' => 200_000]);

        $this->getJson('/api/v1/catalog?income_min=100000')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $rich->id);
    }

    public function test_zone_filter_and_zone_list(): void
    {
        Site::factory()->paid()->zone('ru')->create();
        Site::factory()->paid()->zone('com')->create();
        Site::factory()->paid()->zone('net')->create();

        $this->getJson('/api/v1/catalog?zones=ru,com')
            ->assertOk()
            ->assertJsonCount(2, 'items');

        $zones = $this->getJson('/api/v1/catalog/zones')->assertOk();

        $this->assertSame(['ru', 'com', 'net'], array_column($zones->json('items'), 'value'));
        $this->assertSame('.ru', $zones->json('items.0.label'));
    }

    public function test_category_filter(): void
    {
        $auto = Category::factory()->create(['name' => 'Авто-Мото']);
        $it = Category::factory()->create(['name' => 'Компьютеры и IT']);

        $autoSite = Site::factory()->paid()->create();
        $autoSite->categories()->attach($auto);

        $itSite = Site::factory()->paid()->create();
        $itSite->categories()->attach($it);

        Site::factory()->paid()->create();

        $this->getJson('/api/v1/catalog?categories=avto-moto')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $autoSite->id);

        $this->getJson('/api/v1/catalog?categories=avto-moto,kompiutery-i-it')
            ->assertOk()
            ->assertJsonCount(2, 'items');

        $this->getJson('/api/v1/catalog?categories=unknown')
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_verified_filter(): void
    {
        Site::factory()->paid()->create();
        $verified = Site::factory()->paid()->verified()->create();

        $this->getJson('/api/v1/catalog?verified=1')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $verified->id)
            ->assertJsonPath('items.0.isVerified', true);
    }

    public function test_catalog_is_paginated_and_sorted_by_newest(): void
    {
        Site::factory()->count(27)->paid()->create();

        $response = $this->getJson('/api/v1/catalog');

        $response->assertOk()
            ->assertJsonCount(25, 'items')
            ->assertJsonPath('meta.lastPage', 2)
            ->assertJsonPath('meta.total', 27);

        $secondPage = $this->getJson('/api/v1/catalog?page=2');
        $secondPage->assertOk()->assertJsonCount(2, 'items');

        $beyond = $this->getJson('/api/v1/catalog?page=5');
        $beyond->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_catalog_can_be_sorted_by_price(): void
    {
        $middle = Site::factory()->paid()->create(['price' => 500_000]);
        $cheapest = Site::factory()->paid()->create(['price' => 100_000]);
        $expensive = Site::factory()->paid()->create(['price' => 2_000_000]);

        $this->getJson('/api/v1/catalog?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('items.0.id', $cheapest->id)
            ->assertJsonPath('items.1.id', $middle->id)
            ->assertJsonPath('items.2.id', $expensive->id);

        $this->getJson('/api/v1/catalog?sort=price_desc')
            ->assertOk()
            ->assertJsonPath('items.0.id', $expensive->id)
            ->assertJsonPath('items.2.id', $cheapest->id);
    }

    public function test_catalog_can_be_sorted_by_income(): void
    {
        $middle = Site::factory()->paid()->create(['monthly_revenue' => 50_000]);
        $lowest = Site::factory()->paid()->create(['monthly_revenue' => 10_000]);
        $highest = Site::factory()->paid()->create(['monthly_revenue' => 300_000]);

        $this->getJson('/api/v1/catalog?sort=income_desc')
            ->assertOk()
            ->assertJsonPath('items.0.id', $highest->id)
            ->assertJsonPath('items.1.id', $middle->id)
            ->assertJsonPath('items.2.id', $lowest->id);

        $this->getJson('/api/v1/catalog?sort=income_asc')
            ->assertOk()
            ->assertJsonPath('items.0.id', $lowest->id)
            ->assertJsonPath('items.2.id', $highest->id);
    }

    public function test_unknown_sort_falls_back_to_newest(): void
    {
        $older = Site::factory()->paid()->create(['created_at' => now()->subDay()]);
        $newer = Site::factory()->paid()->create();

        $this->getJson('/api/v1/catalog?sort=unknown')
            ->assertOk()
            ->assertJsonPath('items.0.id', $newer->id)
            ->assertJsonPath('items.1.id', $older->id);
    }

    public function test_search_matches_title_and_domain(): void
    {
        $byTitle = Site::factory()->paid()->create([
            'title' => 'фермерский маркетплейс',
            'domain' => 'example.com',
        ]);
        $byDomain = Site::factory()->paid()->create([
            'title' => 'онлайн-журнал',
            'domain' => 'ferma-dostavka.ru',
        ]);
        Site::factory()->paid()->create([
            'title' => 'автомобильный портал',
            'domain' => 'auto.ru',
        ]);

        $this->getJson('/api/v1/catalog?q=фермер')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $byTitle->id);

        $this->getJson('/api/v1/catalog?q=ferma')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $byDomain->id);
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        Site::factory()->paid()->create(['title' => 'Ферма']);

        $this->getJson('/api/v1/catalog?q=ф')
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->getJson('/api/v1/catalog?q=%20%20')
            ->assertOk()
            ->assertJsonCount(1, 'items');
    }

    public function test_search_is_combined_with_filters(): void
    {
        $matching = Site::factory()->paid()->create([
            'title' => 'фермерский маркетплейс',
            'price' => 100_000,
        ]);
        Site::factory()->paid()->create([
            'title' => 'фермерский маркетплейс',
            'price' => 5_000_000,
        ]);
        Site::factory()->paid()->create([
            'title' => 'городской портал',
            'price' => 100_000,
        ]);

        $this->getJson('/api/v1/catalog?q=фермер&price_max=500000')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $matching->id);
    }

    public function test_invalid_parameters_are_normalized(): void
    {
        Site::factory()->paid()->create();

        $this->getJson('/api/v1/catalog?price_min=-10&price_max=abc&page=0')
            ->assertOk()
            ->assertJsonCount(1, 'items');
    }
}
