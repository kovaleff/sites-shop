<?php

namespace App\Notifications;

use App\Models\SubscriptionPayment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Уведомление администраторов о платеже, который требует ручной проверки.
 *
 * Не наследует MarketplaceNotification: это служебное письмо администратору,
 * а не пользовательская рассылка с настройками и ссылкой отписки.
 */
class PaymentRequiresReview extends Notification implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public SubscriptionPayment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $domain = $this->payment->snapshotDomain() ?? '—';
        $reason = $this->payment->review_reason ?? 'other';

        return (new MailMessage)
            ->subject('Платёж требует проверки: счёт №'.$this->payment->getKey())
            ->greeting('Здравствуйте!')
            ->line("Платёж по объявлению {$domain} требует ручной проверки.")
            ->line('Причина: '.$reason.'.')
            ->line('Сумма: '.number_format((float) $this->payment->amount, 0, '.', ' ').' ₽.')
            ->action(
                'Открыть платёж в админке',
                route('platform.marketplace.payments.show', $this->payment),
            )
            ->line('Примите или отклоните платёж с обязательным комментарием.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_review',
            'title' => 'Платёж требует проверки',
            'message' => 'Счёт №'.$this->payment->getKey().', '.$this->payment->review_reason,
            'url' => route('platform.marketplace.payments.show', $this->payment),
            'paymentId' => $this->payment->getKey(),
            'siteId' => $this->payment->site_id,
        ];
    }
}
