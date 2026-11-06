<?php

namespace Tests\Feature\Seo;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_sitemap_lists_static_pages_and_published_sites(): void
    {
        $site = Site::factory()->paid()->create(['title' => 'Опубликованный лот']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = $response->getContent();

        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString(url('/'), $body);
        $this->assertStringContainsString(url('/pricing'), $body);
        $this->assertStringContainsString(url('/offer'), $body);
        $this->assertStringContainsString(url('/privacy'), $body);
        $this->assertStringContainsString(url($site->canonicalPath()), $body);
    }

    public function test_sitemap_excludes_sites_outside_the_catalog(): void
    {
        $pending = Site::factory()->paid()->pendingModeration()->create();
        $expired = Site::factory()->expired()->create();

        $body = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString(url($pending->canonicalPath()), $body);
        $this->assertStringNotContainsString(url($expired->canonicalPath()), $body);
    }

    public function test_sitemap_cache_is_flushed_when_site_changes(): void
    {
        $site = Site::factory()->paid()->create();

        $this->get('/sitemap.xml')->assertOk();

        $new = Site::factory()->paid()->create();

        $this->get('/sitemap.xml')->assertSee(url($new->canonicalPath()), false);
        $this->get('/sitemap.xml')->assertSee(url($site->canonicalPath()), false);
    }

    public function test_robots_points_to_the_sitemap(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $body = $response->getContent();

        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
        $this->assertStringContainsString('Disallow: /cabinet/', $body);
        $this->assertStringContainsString('Disallow: /admin/', $body);
        $this->assertStringContainsString('Disallow: /api/', $body);
    }
}
