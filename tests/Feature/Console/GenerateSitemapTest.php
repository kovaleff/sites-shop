<?php

namespace Tests\Feature\Console;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_command_builds_and_caches_the_sitemap(): void
    {
        $site = Site::factory()->paid()->create();

        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('Карта сайта обновлена')
            ->assertSuccessful();

        $xml = Cache::get('sitemap.xml');

        $this->assertIsString($xml);
        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString(url($site->canonicalPath()), $xml);
        $this->assertStringContainsString(url('/pricing'), $xml);
    }

    public function test_cached_sitemap_is_served_to_search_engines(): void
    {
        $site = Site::factory()->paid()->create();

        $this->artisan('sitemap:generate')->assertSuccessful();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url($site->canonicalPath()), false);
    }
}
