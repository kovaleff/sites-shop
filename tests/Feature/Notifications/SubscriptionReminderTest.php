<?php

namespace Tests\Feature\Notifications;

use App\Models\Site;
use App\Models\User;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_reminded_seven_days_before_expiration(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->create([
            'paid_until' => today()->addDays(7),
        ]);

        $this->artisan('sites:notify-expiring')->assertSuccessful();

        Notification::assertSentTo(
            $owner,
            SubscriptionExpiring::class,
            fn (SubscriptionExpiring $notification) => $notification->daysLeft === 7
                && $notification->site->is($site),
        );
    }

    public function test_owner_is_reminded_on_the_last_day(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        Site::factory()->for($owner, 'owner')->create([
            'paid_until' => today(),
        ]);

        $this->artisan('sites:notify-expiring')->assertSuccessful();

        Notification::assertSentTo(
            $owner,
            SubscriptionExpiring::class,
            fn (SubscriptionExpiring $notification) => $notification->daysLeft === 0,
        );
    }

    public function test_other_dates_are_ignored(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        Site::factory()->for($owner, 'owner')->create([
            'paid_until' => today()->addDays(3),
        ]);

        $this->artisan('sites:notify-expiring')->assertSuccessful();

        Notification::assertNothingSentTo($owner);
    }

    public function test_reminder_is_sent_once_per_day(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        Site::factory()->for($owner, 'owner')->create([
            'paid_until' => today()->addDays(7),
        ]);

        $this->artisan('sites:notify-expiring')->assertSuccessful();
        $this->artisan('sites:notify-expiring')->assertSuccessful();

        Notification::assertSentToTimes($owner, SubscriptionExpiring::class, 1);
    }

    public function test_disabled_subscription_notifications_are_skipped(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $owner->updateNotificationSettings(['subscription' => false]);

        Site::factory()->for($owner, 'owner')->create([
            'paid_until' => today()->addDays(7),
        ]);

        $this->artisan('sites:notify-expiring')->assertSuccessful();

        Notification::assertNothingSentTo($owner);
    }
}
