<?php

namespace Tests\Feature\Console;

use App\Enums\ModerationStatus;
use App\Models\Site;
use App\Notifications\SiteModerated;
use App\Services\Domain\DomainResolver;
use App\Services\Domain\ReputationChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecheckSiteDomainsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dead_domain_is_rejected_with_reason_and_owner_is_notified(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: false);

        $site = Site::factory()->paid()->create(['domain' => 'dead-site.ru']);

        $this->artisan('sites:recheck-domains')->assertSuccessful();

        $fresh = $site->fresh();

        $this->assertSame(ModerationStatus::Rejected, $fresh->moderation_status);
        $this->assertStringContainsString('Домен недоступен', (string) $fresh->moderation_note);
        $this->assertSame('Домен не найден в DNS.', $fresh->verification_error);
        $this->assertNotNull($fresh->verification_checked_at);

        Notification::assertSentTo(
            $site->owner,
            SiteModerated::class,
            fn (SiteModerated $notification) => $notification->status === ModerationStatus::Rejected
                && $notification->site->is($site),
        );
    }

    public function test_unsafe_domain_is_rejected(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);
        $this->fakeReputation(isUnsafe: true);

        $site = Site::factory()->paid()->create(['domain' => 'malware.ru']);

        $this->artisan('sites:recheck-domains')->assertSuccessful();

        $fresh = $site->fresh();

        $this->assertSame(ModerationStatus::Rejected, $fresh->moderation_status);
        $this->assertStringContainsString('небезопасный', (string) $fresh->moderation_note);
    }

    public function test_alive_safe_domain_keeps_the_site_published(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);
        $this->fakeReputation(isUnsafe: false);

        Http::fake([
            'alive-site.ru/' => Http::response('<html>Магазин</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $site = Site::factory()->paid()->create([
            'domain' => 'alive-site.ru',
            'verification_error' => 'Старая ошибка.',
        ]);

        $this->artisan('sites:recheck-domains')->assertSuccessful();

        $fresh = $site->fresh();

        $this->assertSame(ModerationStatus::Approved, $fresh->moderation_status);
        $this->assertTrue($fresh->isPubliclyVisible());
        $this->assertNull($fresh->verification_error);
        $this->assertNotNull($fresh->verification_checked_at);
        Notification::assertNothingSent();
    }

    public function test_sites_outside_the_catalog_are_not_checked(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: false);

        $pending = Site::factory()->paid()->pendingModeration()->create(['domain' => 'pending.ru']);
        $expired = Site::factory()->expired()->create(['domain' => 'expired.ru']);

        $this->artisan('sites:recheck-domains')->assertSuccessful();

        $this->assertSame(ModerationStatus::Pending, $pending->fresh()->moderation_status);
        $this->assertSame(ModerationStatus::Approved, $expired->fresh()->moderation_status);
        $this->assertNull($pending->fresh()->verification_checked_at);
    }

    private function fakeResolver(bool $resolves): void
    {
        $this->app->instance(DomainResolver::class, new class($resolves) implements DomainResolver
        {
            public function __construct(private readonly bool $resolves) {}

            public function resolves(string $domain): bool
            {
                return $this->resolves;
            }
        });
    }

    private function fakeReputation(bool $isUnsafe): void
    {
        $this->app->instance(ReputationChecker::class, new class($isUnsafe) implements ReputationChecker
        {
            public function __construct(private readonly bool $isUnsafe) {}

            public function isUnsafe(string $domain): bool
            {
                return $this->isUnsafe;
            }
        });
    }
}
