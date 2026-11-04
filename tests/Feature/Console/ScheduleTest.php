<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_and_moderation_tasks_are_scheduled(): void
    {
        $exitCode = Artisan::call('schedule:list');

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();

        foreach ([
            'sites:unpublish-expired',
            'sites:notify-expiring',
            'sites:recheck-domains',
            'sitemap:generate',
            'reports:digest',
            'saved-searches:notify',
            'payments:expire-pending',
        ] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }

    public function test_pending_payments_expire_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'payments:expire-pending'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }
}
