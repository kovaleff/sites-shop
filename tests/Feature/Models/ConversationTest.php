<?php

namespace Tests\Feature\Models;

use App\Enums\ListingStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Site;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_for_creates_a_single_conversation_per_site_and_buyer(): void
    {
        $site = Site::factory()->paid()->create();
        $buyer = User::factory()->create();

        $conversation = Conversation::startFor($site, $buyer);
        $again = Conversation::startFor($site, $buyer);

        $this->assertTrue($conversation->is($again));
        $this->assertSame($site->user_id, $conversation->seller_id);
        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_owner_cannot_start_a_conversation(): void
    {
        $site = Site::factory()->paid()->create();

        $this->expectException(DomainException::class);

        Conversation::startFor($site, $site->owner);
    }

    public function test_non_public_site_rejects_a_new_conversation(): void
    {
        $buyer = User::factory()->create();

        foreach ([
            Site::factory()->unpaid()->create(),
            Site::factory()->expired()->create(),
            Site::factory()->withdrawn()->paid()->create(),
            Site::factory()->sold()->paid()->create(),
        ] as $site) {
            try {
                Conversation::startFor($site, $buyer);
                $this->fail('A conversation must not start for a non-public site.');
            } catch (DomainException) {
                $this->assertSame(0, Conversation::query()->count());
            }
        }
    }

    public function test_participants_are_buyer_and_seller_only(): void
    {
        $conversation = Conversation::factory()->create();
        $stranger = User::factory()->create();

        $this->assertTrue($conversation->isParticipant($conversation->buyer));
        $this->assertTrue($conversation->isParticipant($conversation->seller));
        $this->assertFalse($conversation->isParticipant($stranger));
    }

    public function test_posting_a_message_updates_last_message_at_and_allows_replies(): void
    {
        $site = Site::factory()->paid()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::startFor($site, $buyer);

        $message = $conversation->postMessage($buyer, 'Здравствуйте, сайт ещё продаётся?');
        $reply = $conversation->postMessage($site->owner, 'Да, продаётся.');

        $this->assertSame($buyer->id, $message->sender_id);
        $this->assertSame($site->owner->id, $reply->sender_id);
        $this->assertNotNull($conversation->fresh()->last_message_at);
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_stranger_cannot_post_a_message(): void
    {
        $conversation = Conversation::factory()->create();
        $stranger = User::factory()->create();

        $this->expectException(DomainException::class);

        $conversation->postMessage($stranger, 'Чужой диалог');
    }

    public function test_conversation_becomes_read_only_when_site_is_not_public(): void
    {
        $site = Site::factory()->paid()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::startFor($site, $buyer);
        $conversation->postMessage($buyer, 'Есть вопросы.');

        $site->changeStatus(ListingStatus::Withdrawn);
        $conversation->refresh();

        $this->assertTrue($conversation->isReadOnly());
        $this->assertFalse($conversation->canBeRepliedToBy($buyer));
        $this->assertSame(1, $conversation->messages()->count());

        $this->expectException(DomainException::class);

        $conversation->postMessage($buyer, 'Новое сообщение.');
    }

    public function test_unread_count_excludes_own_messages_and_strangers(): void
    {
        $site = Site::factory()->paid()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::startFor($site, $buyer);

        $conversation->postMessage($buyer, 'Первое сообщение.');
        $conversation->postMessage($buyer, 'Второе сообщение.');

        $this->assertSame(2, $conversation->unreadCountFor($site->owner));
        $this->assertSame(0, $conversation->unreadCountFor($buyer));
        $this->assertSame(0, $conversation->unreadCountFor(User::factory()->create()));
    }

    public function test_mark_read_only_marks_counterpart_messages(): void
    {
        $site = Site::factory()->paid()->create();
        $buyer = User::factory()->create();
        $conversation = Conversation::startFor($site, $buyer);

        $conversation->postMessage($buyer, 'Вопрос покупателя.');
        $conversation->postMessage($site->owner, 'Ответ продавца.');

        $marked = $conversation->markReadFor($buyer);

        $this->assertSame(1, $marked);
        $this->assertSame(0, $conversation->unreadCountFor($buyer));
        $this->assertSame(1, $conversation->unreadCountFor($site->owner));

        $sellerMessage = $conversation->messages()->where('sender_id', $site->owner->id)->first();
        $buyerMessage = $conversation->messages()->where('sender_id', $buyer->id)->first();

        $this->assertTrue($sellerMessage->isRead());
        $this->assertFalse($buyerMessage->isRead());
    }

    public function test_latest_message_returns_the_newest_message(): void
    {
        $conversation = Conversation::factory()->create();

        $first = Message::factory()->for($conversation)->create(['created_at' => now()->subMinute()]);
        $last = Message::factory()->for($conversation)->create(['created_at' => now()]);

        $this->assertTrue($conversation->latestMessage->is($last));
        $this->assertFalse($conversation->latestMessage->is($first));
    }
}
