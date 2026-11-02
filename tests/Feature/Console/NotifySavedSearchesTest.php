<?php

namespace Tests\Feature\Console;

use App\Models\SavedSearch;
use App\Models\Site;
use App\Models\User;
use App\Notifications\SavedSearchMatches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifySavedSearchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_search_sends_a_digest_with_matching_new_sites(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->due()->withFilters([
            'price_min' => 100_000,
        ])->create();

        $matching = Site::factory()->paid()->create(['price' => 200_000, 'created_at' => now()]);
        Site::factory()->paid()->create(['price' => 50_000, 'created_at' => now()]);
        Site::factory()->unpaid()->create(['price' => 300_000, 'created_at' => now()]);

        Artisan::call('saved-searches:notify');

        Notification::assertSentTo(
            $user,
            SavedSearchMatches::class,
            function (SavedSearchMatches $notification) use ($matching): bool {
                return $notification->sites->count() === 1
                    && $notification->sites->first()?->id === $matching->id;
            },
        );

        $this->assertNotNull($search->refresh()->last_notified_at);
    }

    public function test_search_is_skipped_until_its_frequency_is_due(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create(['last_notified_at' => now()]);

        Site::factory()->paid()->create(['created_at' => now()]);

        Artisan::call('saved-searches:notify');

        Notification::assertNothingSentTo($user);
        $this->assertTrue($search->refresh()->last_notified_at->isToday());
    }

    public function test_only_sites_created_after_the_last_digest_are_included(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create([
            'last_notified_at' => now()->subDay(),
        ]);

        $old = Site::factory()->paid()->create(['created_at' => now()->subDays(3)]);
        $new = Site::factory()->paid()->create(['created_at' => now()]);

        Artisan::call('saved-searches:notify');

        Notification::assertSentTo(
            $user,
            SavedSearchMatches::class,
            function (SavedSearchMatches $notification) use ($old, $new): bool {
                return $notification->sites->pluck('id')->all() === [$new->id]
                    && ! $notification->sites->contains('id', $old->id);
            },
        );
    }

    public function test_weekly_search_sends_after_seven_days(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->weekly()->create();

        Site::factory()->paid()->create(['created_at' => now()]);

        Artisan::call('saved-searches:notify');

        Notification::assertSentTo($user, SavedSearchMatches::class);
    }

    public function test_no_matching_sites_keeps_the_threshold_for_the_next_run(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $lastNotifiedAt = now()->subDays(2)->startOfSecond();
        $search = SavedSearch::factory()
            ->for($user)
            ->withFilters(['price_min' => 5_000_000])
            ->create([
                'last_notified_at' => $lastNotifiedAt,
            ]);

        Site::factory()->paid()->create(['price' => 100_000, 'created_at' => now()]);

        Artisan::call('saved-searches:notify');

        Notification::assertNothingSentTo($user);
        $this->assertTrue($search->refresh()->last_notified_at->equalTo($lastNotifiedAt));
    }

    public function test_disabled_notification_type_moves_the_threshold_without_sending(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->updateNotificationSettings(['saved_searches' => false]);

        $search = SavedSearch::factory()->for($user)->due()->create();
        Site::factory()->paid()->create(['created_at' => now()]);

        Artisan::call('saved-searches:notify');

        Notification::assertNothingSentTo($user);
        $this->assertTrue($search->refresh()->last_notified_at->greaterThan(now()->subMinute()));
    }

    public function test_own_sites_are_not_included_in_the_digest(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->due()->create();

        Site::factory()->for($user, 'owner')->paid()->create(['created_at' => now()]);

        Artisan::call('saved-searches:notify');

        Notification::assertNothingSentTo($user);
    }
}
