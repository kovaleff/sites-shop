<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vk.client_id' => '123456',
            'services.vk.client_secret' => 'secret',
            'services.vk.redirect_uri' => '/api/vk/callback',
        ]);
    }

    private function fakeVkLogin(int $vkId = 100500): void
    {
        Http::fake([
            'id.vk.ru/oauth2/auth' => Http::response([
                'access_token' => 'vk-access-token',
                'user_id' => $vkId,
            ]),
            'api.vk.com/method/users.get*' => Http::response(['response' => [[
                'id' => $vkId,
                'first_name' => 'Иван',
                'last_name' => 'Петров',
                'photo_200' => 'https://sun9.example/photo.jpg',
            ]]]),
        ]);
    }

    public function test_guest_gets_200_with_empty_session_state(): void
    {
        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertExactJson([
                'authenticated' => false,
                'user' => null,
            ]);
    }

    public function test_authenticated_user_payload_contains_display_data(): void
    {
        $user = User::factory()->vk()->create([
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'avatar' => 'https://sun9.example/photo.jpg',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.displayName', 'Иван Петров')
            ->assertJsonPath('user.email', 'ivan@example.com')
            ->assertJsonPath('user.avatarUrl', 'https://sun9.example/photo.jpg')
            ->assertJsonPath('user.provider', 'vk');
    }

    public function test_provider_reflects_last_login_time(): void
    {
        $user = User::factory()->vk()->create([
            'ya_id' => 555,
            'vk_last_login_at' => now()->subDay(),
            'ya_last_login_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/user')
            ->assertJsonPath('user.provider', 'yandex');
    }

    public function test_logout_invalidates_session_and_returns_fresh_csrf_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('authenticated', false);

        $this->assertNotEmpty($response->json('csrfToken'));
        $this->assertGuest();
    }

    public function test_login_sets_persistent_remember_cookie(): void
    {
        $this->fakeVkLogin();

        $response = $this->postJson('/api/vk/callback', ['code' => 'authorization-code']);

        $response->assertOk();

        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_'));

        $this->assertNotNull($rememberCookie, 'Ожидалась cookie постоянной сессии «запомнить меня».');
        $this->assertAuthenticated();
    }

    public function test_second_login_does_not_replace_active_session(): void
    {
        $this->fakeVkLogin(100500);
        $this->postJson('/api/vk/callback', ['code' => 'first'])->assertOk();

        $originalId = auth()->id();
        $this->assertNotNull($originalId);

        $this->fakeVkLogin(200600);
        $this->postJson('/api/vk/callback', ['code' => 'second']);

        $this->assertSame($originalId, auth()->id());
    }

    public function test_logout_clears_authentication_for_subsequent_requests(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertGuest();
        $this->getJson('/api/v1/user')->assertJsonPath('authenticated', false);
    }
}
