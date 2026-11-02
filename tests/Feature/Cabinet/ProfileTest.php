<?php

namespace Tests\Feature\Cabinet;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_or_update_profile(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->patchJson('/api/v1/profile', ['name' => 'Новое имя'])->assertUnauthorized();
    }

    public function test_profile_returns_editable_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Иван Петров',
            'bio' => 'Опыт продаж — 5 лет.',
            'city' => 'Москва',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.displayName', 'Иван Петров')
            ->assertJsonPath('user.bio', 'Опыт продаж — 5 лет.')
            ->assertJsonPath('user.city', 'Москва')
            ->assertJsonPath('user.profileUrl', '/seller/'.$user->id);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create(['name' => 'Старое имя']);

        $this->actingAs($user)
            ->patchJson('/api/v1/profile', [
                'name' => 'Новое имя',
                'bio' => '  Рассказ о себе.  ',
                'city' => 'Казань',
            ])
            ->assertOk()
            ->assertJsonPath('user.displayName', 'Новое имя')
            ->assertJsonPath('user.bio', 'Рассказ о себе.')
            ->assertJsonPath('user.city', 'Казань');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Новое имя',
            'city' => 'Казань',
        ]);
    }

    public function test_optional_fields_can_be_cleared(): void
    {
        $user = User::factory()->create([
            'bio' => 'Было описание',
            'city' => 'Москва',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/profile', [
                'name' => $user->name,
                'bio' => null,
                'city' => null,
            ])
            ->assertOk()
            ->assertJsonPath('user.bio', null)
            ->assertJsonPath('user.city', null);
    }

    public function test_profile_update_validates_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/profile', [
                'name' => '',
                'bio' => str_repeat('a', 1001),
                'city' => str_repeat('b', 101),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'bio', 'city']);
    }

    public function test_avatar_is_not_editable_through_profile(): void
    {
        $user = User::factory()->create(['avatar' => 'https://example.com/avatar.jpg']);

        $this->actingAs($user)
            ->patchJson('/api/v1/profile', [
                'name' => 'Иван',
                'avatar' => 'https://evil.example/avatar.jpg',
            ])
            ->assertOk();

        $this->assertSame('https://example.com/avatar.jpg', $user->refresh()->avatar);
    }
}
