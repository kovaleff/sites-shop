<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VkAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK_URL = '/api/vk/callback';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vk.client_id' => '123456',
            'services.vk.client_secret' => 'secret',
            'services.vk.redirect_uri' => self::CALLBACK_URL,
        ]);
    }

    /**
     * Подменить ответы VK ID: обмен кода и users.get.
     *
     * @param  array<string, mixed>  $vkUser
     */
    private function fakeVk(array $vkUser = [], ?string $email = null): void
    {
        $vkUser = array_merge([
            'id' => 100500,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'photo_200' => 'https://sun9.example/photo.jpg',
        ], $vkUser);

        Http::fake([
            'id.vk.ru/oauth2/auth' => Http::response(array_filter([
                'access_token' => 'vk-access-token',
                'user_id' => $vkUser['id'],
                'email' => $email,
            ])),
            'api.vk.com/method/users.get*' => Http::response(['response' => [$vkUser]]),
        ]);
    }

    public function test_first_login_creates_account_with_vk_identity(): void
    {
        $this->fakeVk();

        $response = $this->postJson(self::CALLBACK_URL, [
            'code' => 'authorization-code',
            'device_id' => 'device',
            'code_verifier' => 'verifier',
        ]);

        $response->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('user.displayName', 'Иван Петров')
            ->assertJsonPath('user.avatarUrl', 'https://sun9.example/photo.jpg')
            ->assertJsonPath('user.provider', 'vk');

        $user = User::query()->sole();

        $this->assertSame(100500, (int) $user->vk_id);
        $this->assertSame('Иван Петров', $user->name);
        $this->assertSame('https://sun9.example/photo.jpg', $user->avatar);
        $this->assertNotNull($user->vk_last_login_at);
        $this->assertNotNull($user->password);
        $this->assertSame('vk100500@vk.users', $user->email);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_links_to_existing_account_by_case_insensitive_email(): void
    {
        $existing = User::factory()->create(['email' => 'Ivan@Example.com']);
        $this->fakeVk(['first_name' => 'Иван'], 'ivan@example.com');

        $this->postJson(self::CALLBACK_URL, ['code' => 'authorization-code'])
            ->assertOk()
            ->assertJsonPath('user.id', $existing->id);

        $this->assertSame(1, User::query()->count());

        $existing->refresh();

        $this->assertSame(100500, (int) $existing->vk_id);
        // Регистр существующего адреса не перезаписывается: адреса совпали без учёта регистра.
        $this->assertSame('Ivan@Example.com', $existing->email);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_email_taken_by_another_vk_identity_creates_technical_account(): void
    {
        $existing = User::factory()->vk()->create([
            'email' => 'ivan@example.com',
            'vk_id' => 777,
        ]);

        $this->fakeVk([], 'ivan@example.com');

        $this->postJson(self::CALLBACK_URL, ['code' => 'authorization-code'])->assertOk();

        $existing->refresh();

        $this->assertSame(777, (int) $existing->vk_id);
        $this->assertSame('ivan@example.com', $existing->email);

        $created = User::query()->where('vk_id', 100500)->sole();

        $this->assertSame('vk100500@vk.users', $created->email);
        $this->assertSame(2, User::query()->count());
    }

    public function test_repeat_login_updates_profile_without_duplicate(): void
    {
        $names = ['Иван', 'Пётр'];
        $photos = ['https://sun9.example/old.jpg', 'https://sun9.example/new.jpg'];

        Http::fake([
            'id.vk.ru/oauth2/auth' => Http::response([
                'access_token' => 'vk-access-token',
                'user_id' => 100500,
            ]),
            'api.vk.com/method/users.get*' => function () use (&$names, &$photos) {
                $name = array_shift($names) ?? 'Пётр';
                $photo = array_shift($photos) ?? 'https://sun9.example/new.jpg';

                return Http::response(['response' => [[
                    'id' => 100500,
                    'first_name' => $name,
                    'last_name' => 'Петров',
                    'photo_200' => $photo,
                ]]]);
            },
        ]);

        $this->postJson(self::CALLBACK_URL, ['code' => 'first'])->assertOk();
        $this->postJson(self::CALLBACK_URL, ['code' => 'second'])->assertOk();

        $user = User::query()->sole();

        $this->assertSame(1, User::query()->count());
        $this->assertSame('Пётр Петров', $user->name);
        $this->assertSame('https://sun9.example/new.jpg', $user->avatar);
    }

    public function test_cancelled_login_returns_error_without_creating_account(): void
    {
        $this->postJson(self::CALLBACK_URL, ['error' => 'access_denied'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'vk_cancelled');

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    public function test_missing_code_returns_error_without_creating_account(): void
    {
        $this->postJson(self::CALLBACK_URL, [])
            ->assertStatus(400)
            ->assertJsonPath('error', 'vk_failed');

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    public function test_navigation_failure_redirects_with_auth_error(): void
    {
        $this->get(self::CALLBACK_URL)
            ->assertRedirect('/?auth_error=vk_failed');

        $this->assertGuest();
    }

    public function test_navigation_success_redirects_home_and_authenticates(): void
    {
        $this->fakeVk();

        $this->get(self::CALLBACK_URL.'?code=authorization-code&device_id=device')
            ->assertRedirect('/');

        $this->assertAuthenticated();
    }

    public function test_rate_limit_returns_json_429_for_json_requests(): void
    {
        // Отказы не создают сессию, поэтому лимит считается по адресу запроса.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(self::CALLBACK_URL, ['error' => 'access_denied'])
                ->assertStatus(400);
        }

        $this->postJson(self::CALLBACK_URL, ['error' => 'access_denied'])
            ->assertStatus(429)
            ->assertJsonPath('error', 'too_many_requests')
            ->assertJsonPath('message', 'Слишком много попыток входа. Попробуйте позже');
    }

    public function test_rate_limit_redirects_for_navigation_without_changing_session(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(self::CALLBACK_URL);
        }

        $this->get(self::CALLBACK_URL)
            ->assertRedirect('/?auth_error=too_many_requests');

        $this->assertGuest();
    }

    public function test_exchange_failure_does_not_leak_technical_details(): void
    {
        Http::fake([
            'id.vk.ru/oauth2/auth' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Code is expired',
            ]),
        ]);

        $response = $this->postJson(self::CALLBACK_URL, ['code' => 'expired']);

        $response->assertStatus(400)
            ->assertJsonPath('error', 'vk_failed');

        $this->assertStringNotContainsString('invalid_grant', (string) $response->json('message'));
        $this->assertStringNotContainsString('Code is expired', (string) $response->json('message'));
    }

    public function test_unconfigured_provider_returns_dedicated_error(): void
    {
        config(['services.vk.client_id' => null]);

        $this->postJson(self::CALLBACK_URL, ['code' => 'authorization-code'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'vk_not_configured');

        $this->get(self::CALLBACK_URL.'?code=authorization-code')
            ->assertRedirect('/?auth_error=vk_not_configured');
    }

    public function test_exchange_uses_request_url_as_redirect_uri(): void
    {
        $this->fakeVk();

        $this->postJson(self::CALLBACK_URL, ['code' => 'authorization-code'])->assertOk();

        Http::assertSent(function (ClientRequest $request): bool {
            return str_contains($request->url(), 'id.vk.ru/oauth2/auth')
                && $request['redirect_uri'] === url(self::CALLBACK_URL);
        });
    }
}
