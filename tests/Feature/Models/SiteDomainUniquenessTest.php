<?php

namespace Tests\Feature\Models;

use App\Enums\ListingStatus;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteDomainUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_is_normalized(): void
    {
        $site = Site::factory()->create(['domain' => 'Example.com']);

        $this->assertSame('example.com', $site->domain);

        $another = new Site(['domain' => 'www.Example.com/']);

        $this->assertSame('example.com', $another->domain);
    }

    public function test_duplicate_domain_is_rejected_among_active_sites(): void
    {
        Site::factory()->create(['domain' => 'example.com']);

        $this->expectException(QueryException::class);

        Site::factory()->create(['domain' => 'www.Example.com/']);
    }

    public function test_duplicate_domain_is_rejected_among_active_and_inactive_sites(): void
    {
        Site::factory()->inactive()->create(['domain' => 'example.com']);

        $this->expectException(QueryException::class);

        Site::factory()->create(['domain' => 'example.com']);
    }

    public function test_domain_is_released_after_withdrawn_status(): void
    {
        $site = Site::factory()->create(['domain' => 'example.com']);

        $site->changeStatus(ListingStatus::Withdrawn);

        $replacement = Site::factory()->create(['domain' => 'example.com']);

        $this->assertSame('example.com', $replacement->domain);
    }

    public function test_domain_is_released_after_sold_status(): void
    {
        $site = Site::factory()->create(['domain' => 'example.com']);

        $site->changeStatus(ListingStatus::Sold);

        $replacement = Site::factory()->create(['domain' => 'example.com']);

        $this->assertSame('example.com', $replacement->domain);
    }

    public function test_hidden_sites_may_share_a_domain(): void
    {
        Site::factory()->withdrawn()->create(['domain' => 'example.com']);
        Site::factory()->withdrawn()->create(['domain' => 'example.com']);

        $this->assertSame(2, Site::query()->where('domain', 'example.com')->count());
    }
}
