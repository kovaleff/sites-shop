<?php

namespace Tests\Feature\Notifications;

use App\Enums\ModerationStatus;
use App\Models\Site;
use App\Notifications\SiteModerated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ModerationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_notified_when_listing_is_approved(): void
    {
        Notification::fake();

        $site = Site::factory()->pendingModeration()->create();

        $site->setModeration(ModerationStatus::Approved);

        Notification::assertSentTo(
            $site->owner,
            SiteModerated::class,
            fn (SiteModerated $notification) => $notification->status === ModerationStatus::Approved
                && $notification->site->is($site),
        );
    }

    public function test_owner_is_notified_about_rejection_with_the_reason(): void
    {
        Notification::fake();

        $site = Site::factory()->pendingModeration()->create();

        $site->setModeration(ModerationStatus::Rejected, 'Объявление содержит рекламу сторонних сервисов.');

        Notification::assertSentTo(
            $site->owner,
            SiteModerated::class,
            fn (SiteModerated $notification) => $notification->status === ModerationStatus::Rejected
                && $notification->note === 'Объявление содержит рекламу сторонних сервисов.',
        );
    }

    public function test_returning_to_the_moderation_queue_does_not_notify(): void
    {
        Notification::fake();

        $site = Site::factory()->create();

        $site->setModeration(ModerationStatus::Pending, 'Объявление отредактировано продавцом.');

        Notification::assertNothingSentTo($site->owner);
    }

    public function test_repeated_same_decision_does_not_notify_twice(): void
    {
        Notification::fake();

        $site = Site::factory()->pendingModeration()->create();

        $site->setModeration(ModerationStatus::Approved);
        $site->setModeration(ModerationStatus::Approved);

        Notification::assertSentToTimes($site->owner, SiteModerated::class, 1);
    }

    public function test_notification_is_not_sent_when_the_type_is_disabled(): void
    {
        Notification::fake();

        $site = Site::factory()->pendingModeration()->create();
        $site->owner->updateNotificationSettings(['moderation' => false]);

        $site->setModeration(ModerationStatus::Approved);

        Notification::assertNothingSentTo($site->owner);
    }
}
