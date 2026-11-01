<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\MessagePosted;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\Cache;

/**
 * Письмо второй стороне диалога. Выполняется сразу при отправке сообщения,
 * а само уведомление встаёт в очередь с задержкой — так несколько сообщений
 * за короткое окно превращаются в один дайджест.
 */
class SendNewMessageNotification
{
    public function handle(MessagePosted $event): void
    {
        $conversation = Conversation::query()
            ->with('site')
            ->find($event->message->conversation_id);

        if ($conversation === null) {
            return;
        }

        $recipient = $this->recipient($conversation, (int) $event->message->sender_id);

        if ($recipient === null || ! $recipient->wantsNotification(NotificationType::Messages)) {
            return;
        }

        $minutes = (int) config('notifications.message_digest_minutes', 0);

        if ($minutes > 0) {
            $dedupeKey = "notifications:message:{$conversation->getKey()}:{$recipient->getKey()}";

            if (! Cache::add($dedupeKey, true, now()->addMinutes($minutes))) {
                return;
            }
        }

        $recipient->notify(new NewMessageReceived($conversation));
    }

    /**
     * Письмо получает вторая сторона диалога, а не автор сообщения.
     */
    private function recipient(Conversation $conversation, int $senderId): ?User
    {
        $recipientId = $senderId === $conversation->buyer_id
            ? $conversation->seller_id
            : $conversation->buyer_id;

        return User::query()->find($recipientId);
    }
}
