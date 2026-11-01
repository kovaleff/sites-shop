<?php

namespace App\Listeners;

use App\Enums\ModerationStatus;
use App\Enums\NotificationType;
use App\Events\SiteModerationChanged;
use App\Notifications\SiteModerated;

/**
 * Сообщить продавцу, что модератор одобрил или отклонил объявление.
 */
class SendSiteModeratedNotification
{
    public function handle(SiteModerationChanged $event): void
    {
        // Возврат в очередь модерации — не решение, писать об этом не нужно.
        if ($event->status === ModerationStatus::Pending) {
            return;
        }

        $owner = $event->site->owner;

        if ($owner === null || ! $owner->wantsNotification(NotificationType::Moderation)) {
            return;
        }

        $owner->notify(new SiteModerated($event->site, $event->status, $event->note));
    }
}
