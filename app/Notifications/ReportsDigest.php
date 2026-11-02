<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Утренняя сводка новых жалоб для модераторов площадки.
 */
class ReportsDigest extends Notification implements ShouldQueue
{
    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<int, array{siteId: int, siteTitle: string, reason: string, reporter: ?string, url: string}>  $reports
     */
    public function __construct(
        public array $reports,
        public int $total,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->hasDeliverableEmail()) {
            array_unshift($channels, 'mail');
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Новые жалобы: '.$this->total)
            ->greeting('Здравствуйте!')
            ->line($this->total === 1
                ? 'За прошедший период появилась 1 новая жалоба.'
                : "За прошедший период появилось новых жалоб: {$this->total}.");

        foreach ($this->reports as $report) {
            $line = '• «'.$report['siteTitle'].'» — '.$report['reason'];

            if ($report['reporter'] !== null) {
                $line .= ' ('.$report['reporter'].')';
            }

            $message->line($line.' — '.$report['url']);
        }

        if ($this->total > count($this->reports)) {
            $message->line('Показаны первые '.count($this->reports).' жалоб из '.$this->total.'.');
        }

        return $message->action('Открыть объявления', route('platform.marketplace.sites'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reports_digest',
            'title' => 'Новые жалобы',
            'message' => 'Жалоб за период: '.$this->total,
            'url' => route('platform.marketplace.sites'),
            'total' => $this->total,
            'reports' => $this->reports,
        ];
    }
}
