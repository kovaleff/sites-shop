<?php

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnreadMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/messages/unread')->assertUnauthorized();
    }

    public function test_counts_only_incoming_unread_messages(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        Message::factory()->count(2)->for($conversation)->create();
        Message::factory()->for($conversation)->read()->create();
        Message::factory()->for($conversation)->create(['sender_id' => $seller->id]);

        $this->actingAs($seller)
            ->getJson('/api/v1/messages/unread')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_returns_zero_when_everything_is_read(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();
        Message::factory()->for($conversation)->read()->create();

        $this->actingAs($seller)
            ->getJson('/api/v1/messages/unread')
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_counter_decreases_after_opening_conversation(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();
        Message::factory()->count(3)->for($conversation)->create();

        $this->actingAs($seller)
            ->getJson('/api/v1/messages/unread')
            ->assertJsonPath('count', 3);

        $this->actingAs($seller)
            ->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk();

        $this->actingAs($seller)
            ->getJson('/api/v1/messages/unread')
            ->assertJsonPath('count', 0);
    }

    public function test_counters_are_isolated_between_users(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();
        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();
        Message::factory()->count(2)->for($conversation)->create();

        $this->actingAs($seller)
            ->getJson('/api/v1/messages/unread')
            ->assertJsonPath('count', 2);

        $this->actingAs($stranger)
            ->getJson('/api/v1/messages/unread')
            ->assertJsonPath('count', 0);

        $this->actingAs($buyer)
            ->getJson('/api/v1/messages/unread')
            ->assertJsonPath('count', 0);
    }
}
