<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * Общая часть уведомлений площадки: каналы, учёт пользовательских
 * настроек и ссылка отписки в каждом письме.
 */
abstract class MarketplaceNotification extends Notification implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 60;

    abstract public function type(): NotificationType;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User || ! $notifiable->wantsNotification($this->type())) {
            return [];
        }

        $channels = ['database'];

        if ($notifiable->hasDeliverableEmail()) {
            array_unshift($channels, 'mail');
        }

        return $channels;
    }

    /**
     * Подпись в письме и заголовок List-Unsubscribe для почтовых клиентов.
     */
    protected function withUnsubscribeFooter(MailMessage $message, User $notifiable): MailMessage
    {
        $url = $this->unsubscribeUrl($notifiable);

        return $message
            ->line('Отключить этот тип писем можно в настройках кабинета: '.url('/cabinet/settings/notifications'))
            ->line('Или по ссылке: '.$url)
            ->withSymfonyMessage(function (Email $email) use ($url): void {
                $email->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$url.'>');
            });
    }

    /**
     * Подписанная ссылка отключения уведомления: работает без входа в аккаунт.
     */
    protected function unsubscribeUrl(User $notifiable): string
    {
        return URL::signedRoute('notifications.unsubscribe', [
            'type' => $this->type()->value,
            'user' => $notifiable->getKey(),
        ]);
    }
}
