<?php

namespace Tests\Feature\Console;

use App\Models\SiteReport;
use App\Models\User;
use App\Notifications\ReportsDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportsDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderator_receives_digest_of_new_reports(): void
    {
        Notification::fake();

        $moderator = $this->moderator();
        $report = SiteReport::factory()->create();

        $this->artisan('reports:digest')->assertSuccessful();

        Notification::assertSentTo(
            $moderator,
            ReportsDigest::class,
            fn (ReportsDigest $notification) => $notification->total === 1
                && $notification->reports[0]['siteId'] === $report->site_id
                && $notification->reports[0]['reason'] === $report->reason->label(),
        );
    }

    public function test_regular_users_do_not_receive_the_digest(): void
    {
        Notification::fake();

        $moderator = $this->moderator();
        $regular = User::factory()->create();
        SiteReport::factory()->create();

        $this->artisan('reports:digest')->assertSuccessful();

        Notification::assertSentTo($moderator, ReportsDigest::class);
        Notification::assertNotSentTo($regular, ReportsDigest::class);
    }

    public function test_resolved_reports_are_not_included(): void
    {
        Notification::fake();

        $this->moderator();
        SiteReport::factory()->resolved()->create();

        $this->artisan('reports:digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_digest_is_sent_at_most_once_per_day(): void
    {
        Notification::fake();

        $moderator = $this->moderator();
        SiteReport::factory()->create();

        $this->artisan('reports:digest')->assertSuccessful();
        $this->artisan('reports:digest')->assertSuccessful();

        Notification::assertSentToTimes($moderator, ReportsDigest::class, 1);
    }

    private function moderator(): User
    {
        return User::factory()->create([
            'permissions' => [
                'platform.index' => true,
                'platform.marketplace.sites' => true,
            ],
        ]);
    }
}
