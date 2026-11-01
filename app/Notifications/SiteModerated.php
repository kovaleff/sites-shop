<?php

namespace App\Notifications;

use App\Enums\ModerationStatus;
use App\Enums\NotificationType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class SiteModerated extends MarketplaceNotification
{
    public function __construct(
        public Site $site,
        public ModerationStatus $status,
        public ?string $note = null,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::Moderation;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->status === ModerationStatus::Approved;

        $message = (new MailMessage)
            ->subject(($approved ? 'Объявление одобрено' : 'Объявление отклонено').': '.$this->site->title)
            ->greeting('Здравствуйте!')
            ->line($approved
                ? "Объявление «{$this->site->title}» прошло модерацию и доступно в каталоге."
                : "Объявление «{$this->site->title}» не прошло модерацию.");

        if (! $approved && filled($this->note)) {
            $message->line('Причина: '.$this->note);
        }

        $message->line($approved
            ? 'Проверьте, что подписка оплачена — иначе объявление не показывается покупателям.'
            : 'Исправьте объявление и отправьте его на повторную проверку.')
            ->action('Открыть объявление в кабинете', url('/cabinet/sites/'.$this->site->getKey().'/edit'));

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
            'title' => $this->status === ModerationStatus::Approved
                ? 'Объявление одобрено'
                : 'Объявление отклонено',
            'message' => $this->note,
            'url' => '/cabinet/sites/'.$this->site->getKey().'/edit',
            'siteId' => $this->site->getKey(),
            'status' => $this->status->value,
        ];
    }
}
