<?php

namespace Tests\Feature\Catalog;

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

    public function test_invalid_parameters_are_normalized(): void
    {
        Site::factory()->paid()->create();

        $this->getJson('/api/v1/catalog?price_min=-10&price_max=abc&page=0')
            ->assertOk()
            ->assertJsonCount(1, 'items');
    }
}
