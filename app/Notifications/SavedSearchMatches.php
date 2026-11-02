<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\SavedSearch;
use App\Models\Site;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Дайджест новых лотов, подходящих под сохранённый фильтр.
 */
class SavedSearchMatches extends MarketplaceNotification
{
    /**
     * @param  Collection<int, Site>  $sites
     */
    public function __construct(
        public SavedSearch $search,
        public Collection $sites,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::SavedSearches;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->sites->count();
        $subject = $this->search->name !== null && $this->search->name !== ''
            ? "Новые лоты по фильтру «{$this->search->name}»"
            : 'Новые лоты по вашему фильтру';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Здравствуйте!')
            ->line($count === 1
                ? 'По вашему фильтру появился новый лот:'
                : "По вашему фильтру появились новые лоты ({$count}):");

        $this->sites->take(10)->each(function (Site $site) use ($message): void {
            $message->line(sprintf(
                '• %s (%s) — %s',
                $site->title,
                $site->domain,
                $this->formatPrice((float) $site->price),
            ));
        });

        $message
            ->action('Открыть подборку', $this->search->catalogUrl())
            ->line('Дайджест приходит '.mb_strtolower($this->search->frequency->label()).'.');

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
            'title' => 'Новые лоты по фильтру',
            'message' => Str::limit($this->sites->pluck('title')->implode(', '), 200),
            'url' => $this->search->catalogUrl(),
            'savedSearchId' => $this->search->getKey(),
            'siteIds' => $this->sites->pluck('id')->all(),
            'count' => $this->sites->count(),
        ];
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 0, ',', ' ').' ₽';
    }
}
