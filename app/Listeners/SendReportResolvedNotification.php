<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\SiteReportResolved;
use App\Notifications\ReportResolved;

/**
 * Продавец получает результат рассмотрения жалобы на его объявление.
 */
class SendReportResolvedNotification
{
    public function handle(SiteReportResolved $event): void
    {
        $site = $event->report->site;

        if ($site === null) {
            return;
        }

        $owner = $site->owner;

        if ($owner === null || ! $owner->wantsNotification(NotificationType::Reports)) {
            return;
        }

        $owner->notify(new ReportResolved($event->report, $event->status));
    }
}
