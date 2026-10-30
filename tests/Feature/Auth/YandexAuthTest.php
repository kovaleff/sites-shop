<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class YandexAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK_URL = '/api/ya/callback';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yandex.client_id' => 'ya-client',
            'services.yandex.client_secret' => 'ya-secret',
            'services.yandex.redirect' => self::CALLBACK_URL,
            'services.yandex.allowed_redirects' => 'http://localhost/api/ya/callback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function socialiteUser(array $raw = []): SocialiteUser
    {
        return (new SocialiteUser)->map([
            'id' => '100500',
            'nickname' => 'ivan',
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'avatar' => null,
        ])->setRaw(array_merge([
            'default_avatar_id' => 'avatar-id',
            'is_avatar_empty' => false,
            'default_email' => 'ivan@example.com',
        ], $raw));
    }

    private function fakeSocialite(?SocialiteUser $user = null): void
    {
        Socialite::shouldReceive('driver->redirectUrl->user')
            ->andReturn($user ?? $this->socialiteUser());
    }

    /**
     * @return array<string, mixed>
     */
    private function freshAttempt(): array
    {
        return ['ya_oauth_started_at' => now()->timestamp];
    }

    public function test_first_login_creates_account_with_yandex_identity(): void
    {
        $this->fakeSocialite();

        $this->withSession($this->freshAttempt())
            ->get(self::CALLBACK_URL)
            ->assertRedirect('/');

        $user = User::query()->sole();

        $this->assertSame(100500, (int) $user->ya_id);
        $this->assertSame('Иван Петров', $user->name);
        $this->assertSame('ivan@example.com', $user->email);
        $this->assertSame('https://avatars.yandex.net/get-yapic/avatar-id/islands-200', $user->avatar);
        $this->assertNotNull($user->ya_last_login_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_links_to_existing_account_by_case_insensitive_email(): void
    {
        $existing = User::factory()->create(['email' => 'Ivan@Example.com']);
        $this->fakeSocialite();

        $this->withSession($this->freshAttempt())
            ->get(self::CALLBACK_URL)
            ->assertRedirect('/');

        $this->assertSame(1, User::query()->count());

        $existing->refresh();

        $this->assertSame(100500, (int) $existing->ya_id);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_email_taken_by_another_yandex_identity_creates_technical_account(): void
    {
        $existing = User::factory()->yandex()->create([
            'email' => 'ivan@example.com',
            'ya_id' => 777,
        ]);

        $this->fakeSocialite();

        $this->withSession($this->freshAttempt())
            ->get(self::CALLBACK_URL)
            ->assertRedirect('/');

        $existing->refresh();

        $this->assertSame(777, (int) $existing->ya_id);

        $created = User::query()->where('ya_id', 100500)->sole();

        $this->assertSame('ya100500@ya.users', $created->email);
        $this->assertSame(2, User::query()->count());
    }

    public function test_missing_name_and_avatar_use_defaults(): void
    {
        $this->fakeSocialite(
            (new SocialiteUser)->map([
                'id' => '100500',
                'nickname' => '',
                'name' => '',
                'email' => null,
                'avatar' => null,
            ])->setRaw(['is_avatar_empty' => true]),
        );

        $this->withSession($this->freshAttempt())
            ->get(self::CALLBACK_URL)
            ->assertRedirect('/');

        $user = User::query()->sole();

        $this->assertSame('Пользователь Яндекса', $user->name);
        $this->assertNull($user->avatar);
        $this->assertSame('ya100500@ya.users', $user->email);
    }

    public function test_access_denied_returns_cancelled_without_session(): void
    {
        $this->withSession($this->freshAttempt())
            ->get(self::CALLBACK_URL.'?error=access_denied')
            ->assertRedirect('/?auth_error=cancelled');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_missing_attempt_returns_state_error(): void
    {
        $this->get(self::CALLBACK_URL.'?code=authorization-code')
            ->assertRedirect('/?auth_error=state');

        $this->assertGuest();
    }

    public function test_expired_attempt_returns_state_error(): void
    {
        $this->withSession(['ya_oauth_started_at' => now()->subMinutes(11)->timestamp])
            ->get(self::CALLBACK_URL.'?code=authorization-code')
            ->assertRedirect('/?auth_error=state');

        $this->assertGuest();
    }

    public function test_invalid_state_returns_state_error(): void
    {
        Socialite::shouldReceive('driver->redirectUrl->user')
            ->andThrow(new InvalidStateException('Invalid state'));

        $this->withSession($this->freshAttempt())
            ->get(self::CALLBACK_URL.'?code=authorization-code')
            ->assertRedirect('/?auth_error=state');

        $this->assertGuest();
    }

    public function test_exchange_failure_returns_failed_error(): void
    {
        Socialite::shouldReceive('driver->redirectUrl->user')
            ->andThrow(new \RuntimeException('Token exchange failed'));

        $response = $this->withSession($this->freshAttempt())
            ->get(self::CALLBACK_URL.'?code=authorization-code');

        $response->assertRedirect('/?auth_error=failed');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_redirect_rejects_host_outside_allow_list(): void
    {
        config(['services.yandex.allowed_redirects' => 'https://sites-shop.ru/api/ya/callback']);

        $this->get('/api/ya/redirect')
            ->assertRedirect('/?auth_error=not_configured');
    }

    public function test_redirect_requires_configuration(): void
    {
        config(['services.yandex.client_id' => null]);

        $this->get('/api/ya/redirect')
            ->assertRedirect('/?auth_error=not_configured');
    }

    public function test_redirect_goes_to_yandex_when_configured(): void
    {
        Socialite::shouldReceive('driver->redirectUrl->redirect')
            ->andReturn(redirect('https://oauth.yandex.ru/authorize?client_id=ya-client'));

        $this->get('/api/ya/redirect')
            ->assertRedirect('https://oauth.yandex.ru/authorize?client_id=ya-client');

        $this->assertNotNull(session('ya_oauth_started_at'));
    }

    public function test_callback_rate_limit_redirects_with_auth_error(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withSession($this->freshAttempt())
                ->get(self::CALLBACK_URL.'?error=access_denied')
                ->assertRedirect('/?auth_error=cancelled');
        }

        $this->get(self::CALLBACK_URL.'?error=access_denied')
            ->assertRedirect('/?auth_error=too_many_requests');
    }
}
