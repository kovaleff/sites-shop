<?php

namespace Tests\Feature\Console;

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
        ] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }
}
