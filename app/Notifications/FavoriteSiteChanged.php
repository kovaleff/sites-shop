<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Письмо покупателю: лот из избранного продан или изменил цену.
 */
class FavoriteSiteChanged extends MarketplaceNotification
{
    public function __construct(
        public Site $site,
        public string $change,
        public ?float $oldPrice = null,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::Favorites;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url($this->site->canonicalPath());
        $siteTitle = $this->site->title;

        $message = $this->change === 'sold'
            ? (new MailMessage)
                ->subject("Лот из избранного продан: {$siteTitle}")
                ->greeting('Здравствуйте!')
                ->line("Объявление «{$siteTitle}» ({$this->site->domain}) отмечено как проданное.")
                ->line('Посмотрите другие лоты в каталоге — возможно, найдётся похожий проект.')
                ->action('Открыть объявление', $url)
            : (new MailMessage)
                ->subject("Изменилась цена лота из избранного: {$siteTitle}")
                ->greeting('Здравствуйте!')
                ->line("Цена объявления «{$siteTitle}» ({$this->site->domain}) изменилась.")
                ->line($this->priceLine())
                ->action('Открыть объявление', $url);

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
            'title' => $this->change === 'sold' ? 'Лот из избранного продан' : 'Изменилась цена избранного лота',
            'message' => $this->site->title,
            'url' => $this->site->canonicalPath(),
            'siteId' => $this->site->getKey(),
            'change' => $this->change,
            'oldPrice' => $this->oldPrice,
            'price' => (float) $this->site->price,
        ];
    }

    private function priceLine(): string
    {
        $price = $this->formatPrice((float) $this->site->price);

        if ($this->oldPrice === null || $this->oldPrice <= 0) {
            return "Новая цена: {$price}.";
        }

        return 'Было '.$this->formatPrice($this->oldPrice).", стало {$price}.";
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 0, ',', ' ').' ₽';
    }
}
