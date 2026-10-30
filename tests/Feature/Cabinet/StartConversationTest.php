<?php

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $site = Site::factory()->paid()->create();

        $this->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => 'Здравствуйте',
        ])->assertUnauthorized();
    }

    public function test_first_message_creates_conversation(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();

        $response = $this->actingAs($buyer)->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => 'Здравствуйте, ещё продаёте?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'buyer')
            ->assertJsonPath('data.site.id', $site->id)
            ->assertJsonPath('data.counterparty.id', $seller->id);

        $this->assertDatabaseHas('conversations', [
            'site_id' => $site->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $buyer->id,
            'body' => 'Здравствуйте, ещё продаёте?',
        ]);
    }

    public function test_second_message_reuses_existing_conversation(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();

        $this->actingAs($buyer)->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => 'Первое сообщение',
        ])->assertCreated();

        $this->actingAs($buyer)->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => 'Второе сообщение',
        ])->assertCreated();

        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(2, Conversation::query()->firstOrFail()->messages()->count());
    }

    public function test_owner_cannot_start_conversation_about_own_site(): void
    {
        $owner = User::factory()->create();
        $site = Site::factory()->for($owner, 'owner')->paid()->create();

        $this->actingAs($owner)->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => 'Хочу купить свой сайт',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('siteId');
    }

    public function test_non_public_site_cannot_be_contacted(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->unpaid()->create();

        $this->actingAs($buyer)->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => 'Есть ли скидка?',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('siteId');
    }

    public function test_unknown_site_returns_404(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->postJson('/api/v1/conversations', [
            'siteId' => 999999,
            'body' => 'Здравствуйте',
        ])->assertNotFound();
    }

    public function test_message_text_is_validated(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();

        $this->actingAs($buyer)->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->actingAs($buyer)->postJson('/api/v1/conversations', [
            'siteId' => $site->id,
            'body' => str_repeat('а', 2001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }
}
