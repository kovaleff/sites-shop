<?php

namespace Tests\Feature\Jobs;

use App\Enums\ModerationStatus;
use App\Jobs\VerifySiteDomain;
use App\Models\Site;
use App\Services\Domain\DomainResolver;
use App\Services\Domain\DomainVerifier;
use App\Services\Domain\ReputationChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifySiteDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_check_verifies_and_approves_the_site(): void
    {
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);

        Http::fake([
            'example.ru/' => Http::response('<html><body>Магазин</body></html>', 200, ['Content-Type' => 'text/html']),
            'example.ru/*.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
        ]);

        $site = Site::factory()->pendingModeration()->create([
            'domain' => 'example.ru',
            'verification_filename' => 'check.txt',
        ]);

        $this->runJob($site);

        $site->refresh();

        $this->assertTrue($site->verified);
        $this->assertSame(ModerationStatus::Approved, $site->moderation_status);
        $this->assertNull($site->verification_error);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/check.txt'));
    }

    public function test_missing_verification_file_keeps_site_pending(): void
    {
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);

        Http::fake([
            'example.ru/' => Http::response('<html><body>Магазин</body></html>', 200, ['Content-Type' => 'text/html']),
            'example.ru/*.txt' => Http::response('Not Found', 404),
        ]);

        $site = Site::factory()->pendingModeration()->create([
            'domain' => 'example.ru',
            'verification_filename' => 'missing.txt',
        ]);

        $this->runJob($site);

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame(ModerationStatus::Pending, $site->moderation_status);
        $this->assertSame('Файл подтверждения не найден на сайте.', $site->verification_error);
    }

    public function test_unresolved_domain_keeps_site_pending(): void
    {
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: false);

        $site = Site::factory()->pendingModeration()->create([
            'domain' => 'unknown-domain.ru',
            'verification_filename' => 'check.txt',
        ]);

        $this->runJob($site);

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame(ModerationStatus::Pending, $site->moderation_status);
        $this->assertSame('Домен не найден в DNS.', $site->verification_error);
    }

    public function test_parked_domain_is_not_verified(): void
    {
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);

        Http::fake([
            'parked.ru/' => Http::response('<html>This domain is for sale</html>', 200, ['Content-Type' => 'text/html']),
            'parked.ru/*.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
        ]);

        $site = Site::factory()->pendingModeration()->create([
            'domain' => 'parked.ru',
            'verification_filename' => 'check.txt',
        ]);

        $this->runJob($site);

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame('Сайт недоступен или на нём нет содержимого.', $site->verification_error);
    }

    public function test_unsafe_domain_is_not_verified(): void
    {
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);

        $this->app->instance(ReputationChecker::class, new class implements ReputationChecker
        {
            public function isUnsafe(string $domain): bool
            {
                return true;
            }
        });

        $site = Site::factory()->pendingModeration()->create([
            'domain' => 'malware.ru',
            'verification_filename' => 'check.txt',
        ]);

        $this->runJob($site);

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame('Домен отмечен как небезопасный.', $site->verification_error);
    }

    public function test_recently_registered_domain_is_not_verified(): void
    {
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);
        config(['sites.verification.min_domain_age_days' => 30]);

        Http::fake([
            'rdap.org/*' => Http::response([
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => now()->subDays(3)->toIso8601String()],
                ],
            ]),
        ]);

        $site = Site::factory()->pendingModeration()->create([
            'domain' => 'fresh-domain.ru',
            'verification_filename' => 'check.txt',
        ]);

        $this->runJob($site);

        $site->refresh();

        $this->assertFalse($site->verified);
        $this->assertSame('Домен зарегистрирован слишком недавно.', $site->verification_error);
    }

    public function test_unavailable_rdap_does_not_block_verification(): void
    {
        Http::preventStrayRequests();
        $this->fakeResolver(resolves: true);
        config(['sites.verification.min_domain_age_days' => 30]);

        Http::fake([
            'rdap.org/*' => Http::response('Service Unavailable', 503),
            'example.ru/' => Http::response('<html>Магазин</html>', 200, ['Content-Type' => 'text/html']),
            'example.ru/*.txt' => Http::response('', 200, ['Content-Type' => 'text/plain']),
        ]);

        $site = Site::factory()->pendingModeration()->create([
            'domain' => 'example.ru',
            'verification_filename' => 'check.txt',
        ]);

        $this->runJob($site);

        $this->assertTrue($site->refresh()->verified);
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

    private function runJob(Site $site): void
    {
        (new VerifySiteDomain($site->id))->handle($this->app->make(DomainVerifier::class));
    }
}
