<?php

namespace Tests\Feature\Models;

use App\Enums\ListingStatus;
use App\Models\Site;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: ListingStatus, 1: ListingStatus}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'active to inactive' => [ListingStatus::Active, ListingStatus::Inactive],
            'inactive to active' => [ListingStatus::Inactive, ListingStatus::Active],
            'active to withdrawn' => [ListingStatus::Active, ListingStatus::Withdrawn],
            'inactive to withdrawn' => [ListingStatus::Inactive, ListingStatus::Withdrawn],
            'withdrawn to active' => [ListingStatus::Withdrawn, ListingStatus::Active],
            'withdrawn to inactive' => [ListingStatus::Withdrawn, ListingStatus::Inactive],
            'active to sold' => [ListingStatus::Active, ListingStatus::Sold],
            'inactive to sold' => [ListingStatus::Inactive, ListingStatus::Sold],
            'withdrawn to sold' => [ListingStatus::Withdrawn, ListingStatus::Sold],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_are_applied(ListingStatus $from, ListingStatus $to): void
    {
        $site = $this->siteWithStatus($from);
        $site->changeStatus($to);

        $this->assertSame($to, $site->status);

        if ($to === ListingStatus::Sold) {
            $this->assertNotNull($site->sold_at);
        } else {
            $this->assertNull($site->sold_at);
        }
    }

    public function test_sold_is_terminal(): void
    {
        $site = Site::factory()->sold()->create();

        foreach ([ListingStatus::Active, ListingStatus::Inactive, ListingStatus::Withdrawn] as $target) {
            try {
                $site->changeStatus($target);
                $this->fail("Transition to {$target->value} should be rejected.");
            } catch (DomainException) {
                $this->assertSame(ListingStatus::Sold, $site->status);
            }
        }
    }

    public function test_transition_to_the_same_status_is_a_noop(): void
    {
        $site = Site::factory()->create();

        $site->changeStatus(ListingStatus::Active);

        $this->assertSame(ListingStatus::Active, $site->status);
        $this->assertNull($site->sold_at);
    }

    public function test_invalid_transition_throws_domain_exception(): void
    {
        $site = Site::factory()->sold()->create();

        $this->expectException(DomainException::class);

        $site->changeStatus(ListingStatus::Active);
    }

    private function siteWithStatus(ListingStatus $status): Site
    {
        return match ($status) {
            ListingStatus::Active => Site::factory()->create(),
            ListingStatus::Inactive => Site::factory()->inactive()->create(),
            ListingStatus::Withdrawn => Site::factory()->withdrawn()->create(),
            ListingStatus::Sold => Site::factory()->sold()->create(),
        };
    }
}
