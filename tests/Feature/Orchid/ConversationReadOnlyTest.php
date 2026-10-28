<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;

class ConversationReadOnlyTest extends OrchidTestCase
{
    public function test_conversation_list_shows_site_participants_and_message_count(): void
    {
        $this->actingAs($this->admin());

        $seller = User::factory()->create(['name' => 'Seller Person']);
        $buyer = User::factory()->create(['name' => 'Buyer Person']);
        $site = Site::factory()->for($seller, 'owner')->create(['domain' => 'conversation-site.test']);
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        Message::factory()->count(2)->for($conversation)->create();

        $this->get(route('platform.marketplace.conversations'))
            ->assertOk()
            ->assertSee('conversation-site.test')
            ->assertSee('Buyer Person')
            ->assertSee('Seller Person')
            ->assertSee('Сообщений');
    }

    public function test_conversations_can_be_filtered_by_site(): void
    {
        $this->actingAs($this->admin());

        $matching = Site::factory()->create(['domain' => 'alpha-chat.test']);
        $other = Site::factory()->create(['domain' => 'beta-chat.test']);

        Conversation::factory()->forSite($matching, User::factory()->create())->create();
        Conversation::factory()->forSite($other, User::factory()->create())->create();

        $this->get(route('platform.marketplace.conversations', [
            'site' => 'alpha-chat',
        ]))
            ->assertOk()
            ->assertSee('alpha-chat.test')
            ->assertDontSee('beta-chat.test');
    }

    public function test_conversations_can_be_filtered_by_participant(): void
    {
        $this->actingAs($this->admin());

        $matchingBuyer = User::factory()->create(['name' => 'Unique Buyer']);
        $otherBuyer = User::factory()->create(['name' => 'Other Buyer']);

        Conversation::factory()->forSite(Site::factory()->create(), $matchingBuyer)->create();
        Conversation::factory()->forSite(Site::factory()->create(), $otherBuyer)->create();

        $this->get(route('platform.marketplace.conversations', [
            'participant' => 'Unique Buyer',
        ]))
            ->assertOk()
            ->assertSee('Unique Buyer')
            ->assertDontSee('Other Buyer');
    }

    public function test_conversation_card_shows_messages_in_chronological_order(): void
    {
        $this->actingAs($this->admin());

        $site = Site::factory()->create(['domain' => 'ordered-chat.test']);
        $conversation = Conversation::factory()->forSite($site, User::factory()->create())->create();

        Message::factory()->for($conversation)->create([
            'body' => 'Первое сообщение диалога',
            'created_at' => now()->subMinutes(5),
        ]);

        Message::factory()->for($conversation)->create([
            'body' => 'Второе сообщение диалога',
            'created_at' => now()->subMinutes(2),
        ]);

        $this->get(route('platform.marketplace.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('ordered-chat.test')
            ->assertSeeInOrder(['Первое сообщение диалога', 'Второе сообщение диалога']);
    }

    public function test_conversation_screens_have_no_write_actions(): void
    {
        $this->actingAs($this->admin());

        $conversation = Conversation::factory()
            ->forSite(Site::factory()->create(), User::factory()->create())
            ->create();

        $this->post(route('platform.marketplace.conversations', ['method' => 'save']))
            ->assertStatus(405);

        $this->post(route('platform.marketplace.conversations', ['method' => 'remove']))
            ->assertStatus(405);

        $this->post(route('platform.marketplace.conversations.show', [$conversation, 'save']))
            ->assertStatus(405);

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }
}
