<?php

namespace Tests\Feature\Notifications;

use App\Enums\ReportStatus;
use App\Models\Site;
use App\Models\SiteReport;
use App\Notifications\ReportResolved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_notified_when_the_report_is_accepted(): void
    {
        Notification::fake();

        $site = Site::factory()->paid()->create();
        $report = SiteReport::factory()->for($site)->create();

        $report->resolve(ReportStatus::Resolved);

        Notification::assertSentTo(
            $site->owner,
            ReportResolved::class,
            fn (ReportResolved $notification) => $notification->status === ReportStatus::Resolved
                && $notification->report->is($report),
        );
    }

    public function test_owner_is_notified_when_the_report_is_dismissed(): void
    {
        Notification::fake();

        $site = Site::factory()->paid()->create();
        $report = SiteReport::factory()->for($site)->create();

        $report->resolve(ReportStatus::Dismissed);

        Notification::assertSentTo(
            $site->owner,
            ReportResolved::class,
            fn (ReportResolved $notification) => $notification->status === ReportStatus::Dismissed,
        );
    }

    public function test_already_resolved_report_is_not_notified_again(): void
    {
        Notification::fake();

        $site = Site::factory()->paid()->create();
        $report = SiteReport::factory()->for($site)->create();

        $report->resolve(ReportStatus::Resolved);
        $report->resolve(ReportStatus::Dismissed);

        Notification::assertSentToTimes($site->owner, ReportResolved::class, 1);
    }

    public function test_notification_is_not_sent_when_the_type_is_disabled(): void
    {
        Notification::fake();

        $site = Site::factory()->paid()->create();
        $site->owner->updateNotificationSettings(['reports' => false]);
        $report = SiteReport::factory()->for($site)->create();

        $report->resolve(ReportStatus::Resolved);

        Notification::assertNothingSentTo($site->owner);
    }
}
