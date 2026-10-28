<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserSocialIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_can_be_created_with_vk_id_without_email_or_password(): void
    {
        $user = User::factory()->vk()->create(['vk_id' => 123456789]);

        $this->assertSame(123456789, (int) $user->vk_id);
        $this->assertNull($user->email);
        $this->assertNull($user->password);
        $this->assertDatabaseHas('users', [
            'vk_id' => 123456789,
            'email' => null,
            'password' => null,
        ]);
    }

    public function test_user_can_be_found_by_vk_id(): void
    {
        $user = User::factory()->vk()->create(['vk_id' => 42]);

        $found = User::query()->where('vk_id', 42)->first();

        $this->assertNotNull($found);
        $this->assertTrue($user->is($found));
    }

    public function test_yandex_account_is_not_mixed_with_vk_account(): void
    {
        $vkUser = User::factory()->vk()->create();
        $yandexUser = User::factory()->yandex()->create();

        $this->assertNull($vkUser->ya_id);
        $this->assertNull($yandexUser->vk_id);
        $this->assertNotSame($vkUser->id, $yandexUser->id);
    }

    public function test_two_users_without_email_can_coexist(): void
    {
        User::factory()->vk()->create(['email' => null]);
        User::factory()->yandex()->create(['email' => null]);

        $this->assertSame(2, User::query()->whereNull('email')->count());
    }

    public function test_provider_login_timestamps_are_cast_to_carbon(): void
    {
        $user = User::factory()->vk()->create();

        $this->assertInstanceOf(Carbon::class, $user->vk_last_login_at);
        $this->assertNull($user->ya_last_login_at);
    }

    public function test_avatar_is_stored(): void
    {
        $user = User::factory()->vk()->create([
            'avatar' => 'https://example.com/avatar.png',
        ]);

        $this->assertSame('https://example.com/avatar.png', $user->avatar);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar' => 'https://example.com/avatar.png',
        ]);
    }
}
