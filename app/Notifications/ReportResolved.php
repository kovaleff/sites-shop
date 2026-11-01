<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Enums\ReportStatus;
use App\Models\SiteReport;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class ReportResolved extends MarketplaceNotification
{
    public function __construct(
        public SiteReport $report,
        public ReportStatus $status,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::Reports;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteTitle = $this->report->site?->title ?? 'ваше объявление';
        $accepted = $this->status === ReportStatus::Resolved;

        $message = (new MailMessage)
            ->subject(($accepted ? 'Жалоба принята' : 'Жалоба отклонена').': '.$siteTitle)
            ->greeting('Здравствуйте!')
            ->line($accepted
                ? "Жалоба на объявление «{$siteTitle}» признана обоснованной. Объявление снято с публикации."
                : "Жалоба на объявление «{$siteTitle}» рассмотрена и отклонена. Объявление остаётся в каталоге.")
            ->line($accepted
                ? 'Проверьте текст и условия объявления, исправьте нарушения и отправьте его на повторную модерацию.'
                : 'Ничего делать не нужно — это информационное письмо.')
            ->action('Открыть объявление в кабинете', url('/cabinet/sites/'.$this->report->site_id.'/edit'));

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
            'title' => $this->status === ReportStatus::Resolved
                ? 'Жалоба на объявление принята'
                : 'Жалоба на объявление отклонена',
            'message' => $this->report->reason?->label(),
            'url' => '/cabinet/sites/'.$this->report->site_id.'/edit',
            'siteId' => $this->report->site_id,
            'reportId' => $this->report->getKey(),
            'status' => $this->status->value,
        ];
    }
}
