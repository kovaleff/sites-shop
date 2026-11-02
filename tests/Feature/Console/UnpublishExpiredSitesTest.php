<?php

namespace Tests\Feature\Console;

use App\Enums\ListingStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnpublishExpiredSitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_active_site_becomes_inactive_with_reason(): void
    {
        $site = Site::factory()->expired()->create();

        $this->artisan('sites:unpublish-expired')->assertSuccessful();

        $fresh = $site->fresh();

        $this->assertSame(ListingStatus::Inactive, $fresh->status);
        $this->assertNotNull($fresh->unpublish_reason);
        $this->assertStringContainsString(
            $fresh->paid_until->format('d.m.Y'),
            $fresh->unpublish_reason,
        );
    }

    public function test_expired_site_disappears_from_catalog_but_stays_in_cabinet_with_reason(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->expired()->create();

        $this->artisan('sites:unpublish-expired')->assertSuccessful();

        $this->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->actingAs($owner)
            ->getJson('/api/v1/my/sites')
            ->assertOk()
            ->assertJsonPath('data.0.id', $site->id)
            ->assertJsonPath('data.0.status', 'inactive')
            ->assertJsonPath('data.0.unpublishReason', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_paid_unpaid_and_sold_sites_are_not_touched(): void
    {
        $paid = Site::factory()->paid()->create();
        $unpaid = Site::factory()->unpaid()->create();
        $sold = Site::factory()->sold()->expired()->create();

        $this->artisan('sites:unpublish-expired')->assertSuccessful();

        $this->assertSame(ListingStatus::Active, $paid->fresh()->status);
        $this->assertSame(ListingStatus::Active, $unpaid->fresh()->status);
        $this->assertSame(ListingStatus::Sold, $sold->fresh()->status);
        $this->assertNull($paid->fresh()->unpublish_reason);
        $this->assertNull($unpaid->fresh()->unpublish_reason);
    }

    public function test_reason_is_cleared_after_renewal_and_reactivation(): void
    {
        $site = Site::factory()->expired()->create();

        $this->artisan('sites:unpublish-expired')->assertSuccessful();

        $site->refresh();
        $site->extendSubscription(1);

        $this->assertNull($site->fresh()->unpublish_reason);

        $site->refresh()->changeStatus(ListingStatus::Active);

        $this->assertSame(ListingStatus::Active, $site->fresh()->status);
        $this->assertNull($site->fresh()->unpublish_reason);
    }
}
