<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;

class MessageReadOnlyTest extends OrchidTestCase
{
    public function test_message_list_shows_conversation_sender_and_read_state(): void
    {
        $this->actingAs($this->admin());

        $sender = User::factory()->create(['name' => 'Sender Person']);
        $site = Site::factory()->create(['domain' => 'message-site.test']);
        $conversation = Conversation::factory()->forSite($site, $sender)->create();

        Message::factory()->for($conversation)->create([
            'body' => 'Текст проверяемого сообщения',
        ]);

        $this->get(route('platform.marketplace.messages'))
            ->assertOk()
            ->assertSee('message-site.test')
            ->assertSee('Sender Person')
            ->assertSee('Текст проверяемого сообщения');
    }

    public function test_messages_can_be_filtered_by_conversation(): void
    {
        $this->actingAs($this->admin());

        $matchingConversation = Conversation::factory()
            ->forSite(Site::factory()->create(['domain' => 'alpha-dialog.test']), User::factory()->create())
            ->create();

        $otherConversation = Conversation::factory()
            ->forSite(Site::factory()->create(['domain' => 'beta-dialog.test']), User::factory()->create())
            ->create();

        Message::factory()->for($matchingConversation)->create(['body' => 'Сообщение нужного диалога']);
        Message::factory()->for($otherConversation)->create(['body' => 'Сообщение другого диалога']);

        $this->get(route('platform.marketplace.messages', [
            'conversation' => $matchingConversation->id,
        ]))
            ->assertOk()
            ->assertSee('Сообщение нужного диалога')
            ->assertDontSee('Сообщение другого диалога');
    }

    public function test_messages_can_be_filtered_by_sender(): void
    {
        $this->actingAs($this->admin());

        $sender = User::factory()->create(['name' => 'Unique Sender']);
        $conversation = Conversation::factory()
            ->forSite(Site::factory()->create(), $sender)
            ->create();

        Message::factory()->for($conversation)->create([
            'sender_id' => $sender->id,
            'body' => 'Сообщение нужного отправителя',
        ]);

        Message::factory()->for($conversation)->create([
            'sender_id' => User::factory()->create()->id,
            'body' => 'Сообщение другого отправителя',
        ]);

        $this->get(route('platform.marketplace.messages', [
            'sender' => 'Unique Sender',
        ]))
            ->assertOk()
            ->assertSee('Сообщение нужного отправителя')
            ->assertDontSee('Сообщение другого отправителя');
    }

    public function test_messages_can_be_filtered_by_read_state(): void
    {
        $this->actingAs($this->admin());

        $conversation = Conversation::factory()
            ->forSite(Site::factory()->create(), User::factory()->create())
            ->create();

        Message::factory()->for($conversation)->read()->create([
            'body' => 'Прочитанное сообщение',
        ]);

        Message::factory()->for($conversation)->create([
            'body' => 'Непрочитанное сообщение',
        ]);

        $this->get(route('platform.marketplace.messages', ['read' => 'unread']))
            ->assertOk()
            ->assertSee('Непрочитанное сообщение')
            ->assertDontSee('Прочитанное сообщение');

        $this->get(route('platform.marketplace.messages', ['read' => 'read']))
            ->assertOk()
            ->assertSee('Прочитанное сообщение')
            ->assertDontSee('Непрочитанное сообщение');
    }

    public function test_message_card_shows_full_text_sender_and_read_time(): void
    {
        $this->actingAs($this->admin());

        $sender = User::factory()->create(['name' => 'Card Sender']);
        $conversation = Conversation::factory()
            ->forSite(Site::factory()->create(['domain' => 'card-dialog.test']), $sender)
            ->create();

        $message = Message::factory()->for($conversation)->read()->create([
            'sender_id' => $sender->id,
            'body' => str_repeat('Полный текст сообщения. ', 10),
        ]);

        $this->get(route('platform.marketplace.messages.show', $message))
            ->assertOk()
            ->assertSee('card-dialog.test')
            ->assertSee('Card Sender')
            ->assertSee('Полный текст сообщения.')
            ->assertSee($message->read_at?->format('d.m.Y H:i'));
    }

    public function test_message_screens_have_no_write_actions(): void
    {
        $this->actingAs($this->admin());

        $message = Message::factory()->for(
            Conversation::factory()->forSite(Site::factory()->create(), User::factory()->create())
        )->create();

        $this->post(route('platform.marketplace.messages', ['method' => 'save']))
            ->assertStatus(405);

        $this->post(route('platform.marketplace.messages', ['method' => 'remove']))
            ->assertStatus(405);

        $this->post(route('platform.marketplace.messages.show', [$message, 'save']))
            ->assertStatus(405);

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }
}
