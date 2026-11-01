<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class SubscriptionExpiring extends MarketplaceNotification
{
    /**
     * @param  int  $daysLeft  Сколько дней осталось до окончания; 0 — сегодня.
     */
    public function __construct(
        public Site $site,
        public int $daysLeft,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::Subscription;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $paidUntil = $this->site->paid_until?->format('d.m.Y') ?? '—';

        $message = (new MailMessage)
            ->subject('Подписка на объявление «'.$this->site->title.'» заканчивается')
            ->greeting('Здравствуйте!')
            ->line($this->daysLeft > 0
                ? "Оплаченный период по объявлению «{$this->site->title}» заканчивается через {$this->daysLeft} дн. ({$paidUntil})."
                : "Оплаченный период по объявлению «{$this->site->title}» заканчивается сегодня ({$paidUntil}).")
            ->line('После этой даты объявление перестанет показываться в каталоге, даже если останется в кабинете.')
            ->action('Продлить подписку', url('/cabinet/sites'))
            ->line('Если продление не нужно, просто не оплачивайте — статус объявления можно изменить в кабинете.');

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
            'title' => 'Подписка заканчивается',
            'message' => $this->daysLeft > 0
                ? "Осталось дней: {$this->daysLeft}"
                : 'Оплаченный период заканчивается сегодня.',
            'url' => '/cabinet/sites',
            'siteId' => $this->site->getKey(),
            'paidUntil' => $this->site->paid_until?->toDateString(),
            'daysLeft' => $this->daysLeft,
        ];
    }
}
