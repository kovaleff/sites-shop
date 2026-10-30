<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Orchid\Presenters\UserPresenter;
use Tests\Feature\Orchid\OrchidTestCase;

class UserListAuthColumnsTest extends OrchidTestCase
{
    public function test_user_list_shows_provider_identifiers(): void
    {
        $vkUser = User::factory()->vk()->create(['vk_id' => 100500, 'name' => 'ВК Пользователь']);
        $yaUser = User::factory()->yandex()->create(['ya_id' => 200600, 'name' => 'Яндекс Пользователь']);

        $this->actingAs($this->admin());

        $this->get(route('platform.systems.users'))
            ->assertOk()
            ->assertSee('VK ID')
            ->assertSee('Яндекс ID')
            ->assertSee((string) $vkUser->vk_id)
            ->assertSee((string) $yaUser->ya_id);
    }

    public function test_filter_by_vk_id_finds_the_user(): void
    {
        $target = User::factory()->vk()->create(['vk_id' => 987654, 'name' => 'Найденный Пользователь']);
        User::factory()->vk()->create(['vk_id' => 123123, 'name' => 'Другой Пользователь']);

        $this->actingAs($this->admin());

        $response = $this->get(route('platform.systems.users', [
            'filter' => ['vk_id' => (string) $target->vk_id],
        ]));

        $response->assertOk()
            ->assertSee('Найденный Пользователь')
            ->assertDontSee('Другой Пользователь');
    }

    public function test_filter_by_ya_id_finds_the_user(): void
    {
        $target = User::factory()->yandex()->create(['ya_id' => 555777, 'name' => 'Яндекс Найден']);
        User::factory()->yandex()->create(['ya_id' => 111222, 'name' => 'Яндекс Другой']);

        $this->actingAs($this->admin());

        $response = $this->get(route('platform.systems.users', [
            'filter' => ['ya_id' => (string) $target->ya_id],
        ]));

        $response->assertOk()
            ->assertSee('Яндекс Найден')
            ->assertDontSee('Яндекс Другой');
    }

    public function test_sorting_by_provider_id_is_allowed(): void
    {
        User::factory()->vk()->create(['vk_id' => 900900, 'name' => 'Первый По Сортировке']);
        User::factory()->vk()->create(['vk_id' => 100100, 'name' => 'Второй По Сортировке']);

        $this->actingAs($this->admin());

        $this->get(route('platform.systems.users', ['sort' => 'vk_id']))
            ->assertOk()
            ->assertSee('Второй По Сортировке')
            ->assertSee('Первый По Сортировке');
    }

    public function test_presenter_uses_provider_avatar_when_present(): void
    {
        $user = User::factory()->vk()->create([
            'avatar' => 'https://sun9.example/avatar.jpg',
        ]);

        $this->assertSame('https://sun9.example/avatar.jpg', (new UserPresenter($user))->image());
    }

    public function test_presenter_falls_back_to_gravatar_without_avatar(): void
    {
        $user = User::factory()->create(['avatar' => null]);

        $this->assertStringContainsString('gravatar.com/avatar/', (string) (new UserPresenter($user))->image());
    }
}
