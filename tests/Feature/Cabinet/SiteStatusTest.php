<?php

namespace Tests\Feature\Cabinet;

use App\Enums\ListingStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{ListingStatus, ListingStatus}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'active to inactive' => [ListingStatus::Active, ListingStatus::Inactive],
            'active to withdrawn' => [ListingStatus::Active, ListingStatus::Withdrawn],
            'active to sold' => [ListingStatus::Active, ListingStatus::Sold],
            'inactive to active' => [ListingStatus::Inactive, ListingStatus::Active],
            'inactive to withdrawn' => [ListingStatus::Inactive, ListingStatus::Withdrawn],
            'inactive to sold' => [ListingStatus::Inactive, ListingStatus::Sold],
            'withdrawn to active' => [ListingStatus::Withdrawn, ListingStatus::Active],
            'withdrawn to inactive' => [ListingStatus::Withdrawn, ListingStatus::Inactive],
            'withdrawn to sold' => [ListingStatus::Withdrawn, ListingStatus::Sold],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transition_is_stored(ListingStatus $from, ListingStatus $to): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create(['status' => $from]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}/status", ['status' => $to->value])
            ->assertOk()
            ->assertJsonPath('data.status', $to->value);

        $this->assertSame($to, $site->fresh()->status);
    }

    public function test_sold_site_stays_terminal(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->sold()->create();

        foreach ([ListingStatus::Active, ListingStatus::Inactive, ListingStatus::Withdrawn] as $target) {
            $this->actingAs($owner)
                ->patchJson("/api/v1/my/sites/{$site->id}/status", ['status' => $target->value])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->assertSame(ListingStatus::Sold, $site->fresh()->status);
    }

    public function test_marking_sold_sets_sold_at(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}/status", ['status' => 'sold'])
            ->assertOk();

        $this->assertNotNull($site->fresh()->sold_at);
    }

    public function test_unknown_status_is_rejected(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}/status", ['status' => 'deleted'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_other_sites_are_not_affected(): void
    {
        $owner = User::factory()->create();
        $first = Site::factory()->for($owner, 'owner')->paid()->create();
        $second = Site::factory()->for($owner, 'owner')->paid()->create();
        $paidUntil = $second->paid_until->toDateString();

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$first->id}/status", ['status' => 'inactive'])
            ->assertOk();

        $this->assertSame($paidUntil, $second->fresh()->paid_until->toDateString());
        $this->assertSame(ListingStatus::Active, $second->fresh()->status);
    }

    public function test_foreign_site_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $site = Site::factory()->for($other, 'owner')->create();

        $this->actingAs($owner)
            ->patchJson("/api/v1/my/sites/{$site->id}/status", ['status' => 'inactive'])
            ->assertNotFound();
    }

    public function test_guest_gets_401(): void
    {
        $site = Site::factory()->create();

        $this->patchJson("/api/v1/my/sites/{$site->id}/status", ['status' => 'inactive'])
            ->assertUnauthorized();
    }
}
