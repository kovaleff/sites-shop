<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Conversation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class NewMessageReceived extends MarketplaceNotification
{
    public function __construct(public Conversation $conversation) {}

    public function type(): NotificationType
    {
        return NotificationType::Messages;
    }

    /**
     * Дайджест: несколько сообщений за окно собираются в одно письмо.
     * Отправка откладывается, чтобы не писать на каждую реплику.
     */
    public function withDelay(object $notifiable, string $channel): ?CarbonInterface
    {
        $minutes = (int) config('notifications.message_digest_minutes', 0);

        return $minutes > 0 ? now()->addMinutes($minutes) : null;
    }

    /**
     * К моменту отправки сообщения могли прочитать в кабинете — тогда письмо не нужно.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof User
            && $this->conversation->unreadCountFor($notifiable) > 0;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteTitle = $this->conversation->site?->title ?? 'объявление';
        $unread = $notifiable instanceof User
            ? $this->conversation->unreadCountFor($notifiable)
            : 1;

        $message = (new MailMessage)
            ->subject("Новое сообщение по объявлению «{$siteTitle}»")
            ->greeting('Здравствуйте!')
            ->line($unread > 1
                ? "У вас {$unread} непрочитанных сообщений по объявлению «{$siteTitle}»."
                : "Вам написали по объявлению «{$siteTitle}».")
            ->line($this->preview())
            ->action('Открыть диалог', url('/cabinet/messages/'.$this->conversation->getKey()))
            ->line('Ответить можно в личном кабинете.');

        return $notifiable instanceof User
            ? $this->withUnsubscribeFooter($message, $notifiable)
            : $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'title' => 'Новое сообщение',
            'message' => Str::limit((string) $this->conversation->latestMessage?->body, 200),
            'url' => '/cabinet/messages/'.$this->conversation->getKey(),
            'conversationId' => $this->conversation->getKey(),
            'siteId' => $this->conversation->site_id,
        ];
    }

    /**
     * Последняя реплика собеседника для превью в письме.
     */
    protected function preview(): string
    {
        $body = trim((string) $this->conversation->latestMessage?->body);

        if ($body === '') {
            return 'Откройте диалог, чтобы прочитать сообщение.';
        }

        return '«'.Str::limit($body, 300).'»';
    }
}
