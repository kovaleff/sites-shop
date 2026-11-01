<?php

namespace Tests\Feature\Notifications;

use App\Models\Conversation;
use App\Models\Site;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MessageNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_is_notified_about_a_new_message_from_the_buyer(): void
    {
        Notification::fake();

        [$seller, $buyer, $conversation] = $this->conversation();

        $conversation->postMessage($buyer, 'Здравствуйте, сайт ещё продаётся?');

        Notification::assertSentTo($seller, NewMessageReceived::class);
        Notification::assertNotSentTo($buyer, NewMessageReceived::class);
    }

    public function test_buyer_is_notified_about_the_seller_reply(): void
    {
        Notification::fake();

        [$seller, $buyer, $conversation] = $this->conversation();

        $conversation->postMessage($seller, 'Да, продаётся.');

        Notification::assertSentTo($buyer, NewMessageReceived::class);
        Notification::assertNotSentTo($seller, NewMessageReceived::class);
    }

    public function test_several_messages_in_digest_window_produce_one_notification(): void
    {
        Notification::fake();
        config(['notifications.message_digest_minutes' => 10]);

        [$seller, $buyer, $conversation] = $this->conversation();

        $conversation->postMessage($buyer, 'Первое сообщение');
        $conversation->postMessage($buyer, 'Второе сообщение');

        Notification::assertSentToTimes($seller, NewMessageReceived::class, 1);
    }

    public function test_zero_digest_window_notifies_about_each_message(): void
    {
        Notification::fake();
        config(['notifications.message_digest_minutes' => 0]);

        [$seller, $buyer, $conversation] = $this->conversation();

        $conversation->postMessage($buyer, 'Первое');
        $conversation->postMessage($buyer, 'Второе');

        Notification::assertSentToTimes($seller, NewMessageReceived::class, 2);
    }

    public function test_notification_is_not_sent_when_the_type_is_disabled(): void
    {
        Notification::fake();

        [$seller, $buyer, $conversation] = $this->conversation();
        $seller->updateNotificationSettings(['messages' => false]);

        $conversation->postMessage($buyer, 'Здравствуйте');

        Notification::assertNothingSentTo($seller);
    }

    public function test_notification_is_skipped_when_messages_are_already_read(): void
    {
        Notification::fake();

        [$seller, $buyer, $conversation] = $this->conversation();

        $conversation->postMessage($buyer, 'Здравствуйте');
        $conversation->markReadFor($seller);

        $notification = new NewMessageReceived($conversation->fresh());

        $this->assertFalse($notification->shouldSend($seller, 'mail'));
    }

    public function test_accounts_without_a_real_email_get_only_database_channel(): void
    {
        $seller = User::factory()->vk()->create();
        $buyer = User::factory()->create();

        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        $notification = new NewMessageReceived($conversation);

        $this->assertSame(['database'], $notification->via($seller));
        $this->assertNull($seller->email);
    }

    public function test_accounts_with_an_email_get_both_channels(): void
    {
        [$seller, , $conversation] = $this->conversation();

        $notification = new NewMessageReceived($conversation);

        $this->assertSame(['mail', 'database'], $notification->via($seller));
    }

    public function test_notification_sends_a_rendered_email_with_unsubscribe_header(): void
    {
        [, $buyer, $conversation] = $this->conversation();
        $seller = $conversation->site->owner;

        $conversation->postMessage($buyer, 'Сайт ещё продаётся?');

        $transport = Mail::mailer()->getSymfonyTransport();

        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());

        $email = $transport->messages()->last()->getOriginalMessage();

        $this->assertSame($seller->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('Новое сообщение', $email->getSubject());
        $this->assertNotNull($email->getHeaders()->get('List-Unsubscribe'));

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $seller->getMorphClass(),
            'notifiable_id' => $seller->getKey(),
        ]);
    }

    /**
     * @return array{User, User, Conversation}
     */
    private function conversation(): array
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();

        $site = Site::factory()->for($seller, 'owner')->paid()->create();
        $conversation = Conversation::factory()->forSite($site, $buyer)->create();

        return [$seller, $buyer, $conversation];
    }
}
