<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Enums\NotificationType;
use App\Models\Site;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NotifyExpiringSubscriptions extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sites:notify-expiring';

    /**
     * @var string
     */
    protected $description = 'Напомнить продавцам об истекающей подписке на объявления';

    public function handle(): int
    {
        $sent = 0;

        foreach ($this->reminderDays() as $daysLeft) {
            $date = today()->addDays($daysLeft)->toDateString();

            Site::query()
                ->whereIn('status', [ListingStatus::Active, ListingStatus::Inactive])
                ->whereDate('paid_until', $date)
                ->with('owner')
                ->each(function (Site $site) use ($daysLeft, &$sent): void {
                    $owner = $site->owner;

                    if ($owner === null || ! $owner->wantsNotification(NotificationType::Subscription)) {
                        return;
                    }

                    // Команда может запуститься повторно — письмо за день уходит один раз.
                    $dedupeKey = "notifications:subscription:{$site->getKey()}:{$site->paid_until?->toDateString()}:{$daysLeft}";

                    if (! Cache::add($dedupeKey, true, now()->addDay())) {
                        return;
                    }

                    $owner->notify(new SubscriptionExpiring($site, $daysLeft));
                    $sent++;
                });
        }

        $this->info("Отправлено напоминаний: {$sent}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function reminderDays(): array
    {
        return collect(config('notifications.subscription_reminder_days', [7, 1, 0]))
            ->map(fn (mixed $days): int => (int) $days)
            ->unique()
            ->values()
            ->all();
    }
}
