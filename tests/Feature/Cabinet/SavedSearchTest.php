<?php

namespace Tests\Feature\Cabinet;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_saved_searches(): void
    {
        $this->getJson('/api/v1/saved-searches')->assertUnauthorized();
        $this->postJson('/api/v1/saved-searches', [])->assertUnauthorized();
        $this->patchJson('/api/v1/saved-searches/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/saved-searches/1')->assertUnauthorized();
    }

    public function test_user_can_save_a_filter_with_frequency_and_get_a_catalog_link(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/saved-searches', [
            'name' => 'До 500 тысяч',
            'frequency' => 'weekly',
            'filters' => [
                'price_min' => 100_000,
                'price_max' => 500_000,
                'zones' => ['ru', 'com'],
                'categories' => ['auto-moto'],
                'verified' => true,
                'q' => 'ферма',
                'sort' => 'price_asc',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'До 500 тысяч')
            ->assertJsonPath('data.frequency', 'weekly')
            ->assertJsonPath('data.frequencyLabel', 'Раз в неделю')
            ->assertJsonPath(
                'data.summary',
                'Цена: 100 000 ₽–500 000 ₽ · Зоны: .ru, .com · Тематик: 1 · Только проверенные · Поиск: «ферма»',
            );

        $this->assertStringContainsString('price_min=100000', $response->json('data.url'));
        $this->assertStringContainsString('zones=ru%2Ccom', $response->json('data.url'));
        $this->assertStringContainsString('verified=1', $response->json('data.url'));

        $search = SavedSearch::query()->firstOrFail();

        $this->assertNotNull($search->last_notified_at);
        $this->assertSame(0, $search->filters['income_min']);
        $this->assertSame(1_000_000, $search->filters['income_max']);
        $this->assertSame(['auto-moto'], $search->filters['categories']);
        $this->assertSame('price_asc', $search->filters['sort']);
    }

    public function test_filter_payload_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/saved-searches', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['frequency', 'filters']);

        $this->actingAs($user)
            ->postJson('/api/v1/saved-searches', [
                'frequency' => 'hourly',
                'filters' => ['sort' => 'unknown'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['frequency', 'filters.sort']);

        $this->actingAs($user)
            ->postJson('/api/v1/saved-searches', [
                'frequency' => 'daily',
                'filters' => ['price_min' => -1],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filters.price_min']);
    }

    public function test_index_returns_only_current_user_searches(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        SavedSearch::factory()->for($user)->create(['name' => 'Моя подписка']);
        SavedSearch::factory()->for($other)->create(['name' => 'Чужая подписка']);

        $response = $this->actingAs($user)->getJson('/api/v1/saved-searches');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Моя подписка', $response->json('data.0.name'));
    }

    public function test_user_can_change_frequency_and_name(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/saved-searches/'.$search->id, [
                'frequency' => 'weekly',
                'name' => 'Раз в неделю',
            ])
            ->assertOk()
            ->assertJsonPath('data.frequency', 'weekly')
            ->assertJsonPath('data.name', 'Раз в неделю');

        $this->assertSame('weekly', $search->refresh()->frequency->value);
    }

    public function test_foreign_search_cannot_be_changed_or_deleted(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $search = SavedSearch::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->patchJson('/api/v1/saved-searches/'.$search->id, ['frequency' => 'weekly'])
            ->assertNotFound();

        $this->actingAs($intruder)
            ->deleteJson('/api/v1/saved-searches/'.$search->id)
            ->assertNotFound();

        $this->assertDatabaseHas('saved_searches', ['id' => $search->id]);
    }

    public function test_user_can_delete_own_search(): void
    {
        $user = User::factory()->create();
        $search = SavedSearch::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson('/api/v1/saved-searches/'.$search->id)
            ->assertOk();

        $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
    }
}
