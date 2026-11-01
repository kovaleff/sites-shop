<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_listing_page_is_rendered_with_meta_tags(): void
    {
        $site = Site::factory()->paid()->create([
            'title' => 'Магазин товаров для дома',
            'domain' => 'dom-shop.ru',
        ]);

        $response = $this->get($site->canonicalPath());

        $response->assertOk();
        $response->assertSee('<title>', false);
        $response->assertSee('Магазин товаров для дома', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee(url($site->canonicalPath()), false);
        $response->assertSee('og:title', false);
        $response->assertSee('og:image', false);
        $response->assertSee('summary_large_image', false);
        $response->assertSee('index, follow', false);
    }

    public function test_listing_page_contains_product_json_ld(): void
    {
        $site = Site::factory()->paid()->create(['price' => 1_250_000]);

        $response = $this->get($site->canonicalPath());

        $response->assertSee('application/ld+json', false);
        $response->assertSee('Product', false);
        $response->assertSee('priceCurrency', false);
        $response->assertSee('1250000', false);
    }

    public function test_listing_page_hydrates_spa_state(): void
    {
        $site = Site::factory()->paid()->create(['title' => 'Лот для гидратации']);

        $response = $this->get($site->canonicalPath());

        $response->assertSee('__INITIAL_STATE__', false);
        $response->assertSee('Лот для гидратации', false);
        $response->assertSee($site->id.'-'.$site->slug(), false);
    }

    public function test_listing_page_includes_categories(): void
    {
        $site = Site::factory()->paid()->create();
        $category = Category::factory()->create(['name' => 'Компьютеры и IT']);
        $site->categories()->attach($category);

        $response = $this->get($site->canonicalPath());

        $response->assertSee('Компьютеры и IT', false);
    }

    public function test_outdated_slug_redirects_to_canonical_permanently(): void
    {
        $site = Site::factory()->paid()->create(['title' => 'Название']);

        $this->get('/site/'.$site->id.'-staroe-nazvanie')
            ->assertStatus(301)
            ->assertRedirect($site->canonicalPath());
    }

    public function test_site_outside_the_catalog_is_not_found(): void
    {
        $site = Site::factory()->paid()->pendingModeration()->create();

        $this->get($site->canonicalPath())->assertNotFound();
    }

    public function test_expired_subscription_is_not_found(): void
    {
        $site = Site::factory()->expired()->create();

        $this->get($site->canonicalPath())->assertNotFound();
    }

    public function test_catalog_page_has_its_own_meta(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('маркетплейс готовых сайтов', false)
            ->assertSee('og:type', false);
    }

    public function test_static_pages_have_unique_titles(): void
    {
        $this->get('/how-it-works')
            ->assertOk()
            ->assertSee('Как это работает — Sites-shop', false);

        $this->get('/pricing')
            ->assertOk()
            ->assertSee('Тарифы — Sites-shop', false);
    }

    public function test_cabinet_is_closed_from_indexing(): void
    {
        $this->get('/cabinet/sites')
            ->assertOk()
            ->assertSee('noindex, follow', false);
    }
}
