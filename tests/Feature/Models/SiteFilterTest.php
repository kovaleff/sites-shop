<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_of_categories_matches_any_selected_category(): void
    {
        $first = Category::factory()->create();
        $second = Category::factory()->create();
        $third = Category::factory()->create();

        $firstSite = Site::factory()->create();
        $firstSite->categories()->attach($first);

        $secondSite = Site::factory()->create();
        $secondSite->categories()->attach($second);

        $thirdSite = Site::factory()->create();
        $thirdSite->categories()->attach($third);

        $ids = Site::query()->ofCategories([$first->id, $second->id])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$firstSite->id, $secondSite->id], $ids);
        $this->assertNotContains($thirdSite->id, $ids);
    }

    public function test_of_categories_with_empty_array_does_not_restrict(): void
    {
        Site::factory()->count(3)->create();

        $this->assertSame(3, Site::query()->ofCategories([])->count());
    }

    public function test_price_and_revenue_ranges_are_inclusive(): void
    {
        $low = Site::factory()->create(['price' => 1000, 'monthly_revenue' => 100]);
        $middle = Site::factory()->create(['price' => 2000, 'monthly_revenue' => 200]);
        $high = Site::factory()->create(['price' => 3000, 'monthly_revenue' => 300]);

        $this->assertEqualsCanonicalizing(
            [$low->id, $middle->id, $high->id],
            Site::query()->priceBetween(1000, 3000)->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$low->id, $middle->id],
            Site::query()->priceBetween(null, 2000)->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$middle->id, $high->id],
            Site::query()->priceBetween(2000, null)->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$middle->id],
            Site::query()->revenueBetween(200, 200)->pluck('id')->all(),
        );
    }

    public function test_domain_contains_is_case_insensitive_and_partial(): void
    {
        $matching = Site::factory()->create(['domain' => 'my-example-site.com']);
        Site::factory()->create(['domain' => 'other.com']);

        $ids = Site::query()->domainContains('EXAMPLE')->pluck('id')->all();

        $this->assertSame([$matching->id], $ids);
    }

    public function test_filters_are_combined_with_and(): void
    {
        $category = Category::factory()->create();

        $matching = Site::factory()->create(['price' => 5000, 'domain' => 'shop-one.example.com']);
        $matching->categories()->attach($category);

        $wrongCategory = Site::factory()->create(['price' => 5000, 'domain' => 'shop-two.example.com']);
        $wrongCategory->categories()->attach(Category::factory()->create());

        $wrongPrice = Site::factory()->create(['price' => 9000, 'domain' => 'shop-three.example.com']);
        $wrongPrice->categories()->attach($category);

        $ids = Site::query()
            ->ofCategories([$category->id])
            ->priceBetween(1000, 6000)
            ->domainContains('shop')
            ->pluck('id')
            ->all();

        $this->assertSame([$matching->id], $ids);
    }

    public function test_newest_first_orders_by_creation_date(): void
    {
        $oldest = Site::factory()->create(['created_at' => now()->subDays(2)]);
        $middle = Site::factory()->create(['created_at' => now()->subDay()]);
        $newest = Site::factory()->create(['created_at' => now()]);

        $ids = Site::query()->newestFirst()->pluck('id')->all();

        $this->assertSame([$newest->id, $middle->id, $oldest->id], $ids);
    }

    public function test_catalog_page_size_is_twenty_five(): void
    {
        $this->assertSame(25, Site::CATALOG_PER_PAGE);

        Site::factory()->count(30)->create();

        $page = Site::query()->paginate(Site::CATALOG_PER_PAGE);

        $this->assertCount(25, $page->items());
        $this->assertSame(30, $page->total());
        $this->assertSame(2, $page->lastPage());
    }
}
