<?php

namespace Tests\Feature\Models;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_site_with_current_or_future_payment_is_visible(): void
    {
        $today = Site::factory()->create(['paid_until' => today()]);
        $future = Site::factory()->create(['paid_until' => today()->addWeek()]);

        $visibleIds = Site::query()->publiclyVisible()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$today->id, $future->id], $visibleIds);
        $this->assertTrue($today->isPubliclyVisible());
        $this->assertTrue($future->isPubliclyVisible());
    }

    public function test_active_site_without_payment_is_hidden(): void
    {
        $site = Site::factory()->unpaid()->create();

        $this->assertFalse($site->isPubliclyVisible());
        $this->assertSame(0, Site::query()->publiclyVisible()->count());
    }

    public function test_active_site_with_expired_payment_is_hidden(): void
    {
        $site = Site::factory()->expired()->create();

        $this->assertFalse($site->isPubliclyVisible());
        $this->assertSame(0, Site::query()->publiclyVisible()->count());
    }

    public function test_non_active_sites_are_hidden_even_with_active_payment(): void
    {
        Site::factory()->inactive()->paid()->create();
        Site::factory()->withdrawn()->paid()->create();
        Site::factory()->sold()->paid()->create();

        $this->assertSame(0, Site::query()->publiclyVisible()->count());
    }

    public function test_owned_by_returns_all_sites_regardless_of_visibility(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Site::factory()->for($owner, 'owner')->paid()->create();
        Site::factory()->for($owner, 'owner')->unpaid()->create();
        Site::factory()->for($owner, 'owner')->sold()->create();
        Site::factory()->for($other, 'owner')->paid()->create();

        $ownedIds = Site::query()->ownedBy($owner)->pluck('id');

        $this->assertCount(3, $ownedIds);
        $this->assertSame(1, Site::query()->ownedBy($other)->count());
    }
}
