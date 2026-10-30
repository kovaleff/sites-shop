<?php

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
    }

    public function test_list_contains_only_participant_conversations(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();

        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();
        Message::factory()->for($conversation)->create();

        $otherSeller = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $foreignSite = Site::factory()->for($otherSeller, 'owner')->paid()->create();
        Conversation::factory()->forSite($foreignSite, $otherBuyer)->create();

        $this->actingAs($seller)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.role', 'seller');

        $this->actingAs($stranger)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_is_sorted_by_last_message(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $first = Site::factory()->for($seller, 'owner')->paid()->create();
        $second = Site::factory()->for($seller, 'owner')->paid()->create();

        $older = Conversation::factory()->forSite($first, $buyer)->create([
            'last_message_at' => now()->subDay(),
        ]);
        $newer = Conversation::factory()->forSite($second, $buyer)->create([
            'last_message_at' => now(),
        ]);

        $this->actingAs($seller)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_opening_conversation_marks_incoming_messages_read(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        $incoming = Message::factory()->for($conversation)->create(['body' => 'Здравствуйте']);
        $own = Message::factory()->for($conversation)->create([
            'sender_id' => $seller->id,
            'body' => 'Добрый день',
        ]);

        $response = $this->actingAs($seller)
            ->getJson("/api/v1/conversations/{$conversation->id}");

        $response->assertOk()
            ->assertJsonPath('messages.0.body', 'Здравствуйте')
            ->assertJsonPath('messages.0.isMine', false)
            ->assertJsonPath('messages.1.isMine', true);

        $this->assertNotNull($incoming->fresh()->read_at);
        $this->assertNull($own->fresh()->read_at);
    }

    public function test_foreign_conversation_returns_404(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertNotFound();
    }

    public function test_reply_is_stored_and_returned(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($seller)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Отвечаю на вопрос',
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Отвечаю на вопрос')
            ->assertJsonPath('data.isMine', true);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $seller->id,
            'body' => 'Отвечаю на вопрос',
        ]);
    }

    public function test_message_length_is_validated(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($seller)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->actingAs($seller)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => str_repeat('а', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_reply_to_non_public_site_is_rejected(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->unpaid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        $this->actingAs($seller)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Есть ли ещё вопросы?',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_conversation_of_non_public_site_is_read_only_in_resource(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->unpaid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();
        Message::factory()->for($conversation)->create();

        $this->actingAs($seller)
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.isReadOnly', true);
    }
}
