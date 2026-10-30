<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthErrorTest extends TestCase
{
    use RefreshDatabase;

    private const VK_CALLBACK = '/api/vk/callback';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vk.client_id' => '123456',
            'services.vk.client_secret' => 'secret',
            'services.vk.redirect_uri' => self::VK_CALLBACK,
        ]);
    }

    /**
     * @param  callable(): void  $request
     */
    private function exhaustRateLimit(callable $request): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $request();
        }
    }

    public function test_json_request_gets_429_with_contract_payload(): void
    {
        $this->exhaustRateLimit(fn () => $this->postJson(self::VK_CALLBACK, ['error' => 'access_denied']));

        $this->postJson(self::VK_CALLBACK, ['error' => 'access_denied'])
            ->assertStatus(429)
            ->assertExactJson([
                'error' => 'too_many_requests',
                'message' => 'Слишком много попыток входа. Попробуйте позже',
            ]);
    }

    public function test_navigation_gets_redirect_with_auth_error_code(): void
    {
        $this->exhaustRateLimit(fn () => $this->get(self::VK_CALLBACK.'?error=access_denied'));

        $this->get(self::VK_CALLBACK.'?error=access_denied')
            ->assertRedirect('/?auth_error=too_many_requests');
    }

    public function test_rate_limit_does_not_change_guest_session(): void
    {
        $this->exhaustRateLimit(fn () => $this->get(self::VK_CALLBACK.'?error=access_denied'));

        $this->get(self::VK_CALLBACK.'?error=access_denied')
            ->assertRedirect('/?auth_error=too_many_requests');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_rate_limit_does_not_drop_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->exhaustRateLimit(fn () => $this->actingAs($user)->get(self::VK_CALLBACK.'?error=access_denied'));

        $this->actingAs($user)
            ->get(self::VK_CALLBACK.'?error=access_denied')
            ->assertRedirect('/?auth_error=too_many_requests');

        $this->assertAuthenticatedAs($user);
    }

    public function test_error_messages_do_not_expose_technical_details(): void
    {
        config(['services.vk.client_id' => null]);

        $response = $this->postJson(self::VK_CALLBACK, ['code' => 'secret-authorization-code']);

        $message = (string) $response->json('message');

        $response->assertStatus(400);

        foreach (['secret-authorization-code', 'client_secret', 'Exception', 'secret'] as $needle) {
            $this->assertStringNotContainsString($needle, $message);
        }
    }

    public function test_failures_are_logged_without_secrets(): void
    {
        config(['services.vk.client_id' => null]);

        Log::spy();

        $this->postJson(self::VK_CALLBACK, ['code' => 'secret-authorization-code']);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $channel, array $context): bool {
                $payload = json_encode($context, JSON_UNESCAPED_UNICODE);

                return $channel === 'VK OAuth error'
                    && ! str_contains($payload, 'secret-authorization-code')
                    && ! str_contains($payload, 'client_secret');
            });
    }

    public function test_unknown_auth_error_code_is_not_echoed_to_the_page(): void
    {
        $this->get('/?auth_error=raw_provider_error')
            ->assertOk();
    }
}
