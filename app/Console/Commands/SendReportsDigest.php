<?php

namespace App\Console\Commands;

use App\Models\SiteReport;
use App\Models\User;
use App\Notifications\ReportsDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendReportsDigest extends Command
{
    /**
     * Сколько жалоб показывать в одном письме.
     */
    private const MAX_REPORTS_IN_MAIL = 20;

    /**
     * @var string
     */
    protected $signature = 'reports:digest';

    /**
     * @var string
     */
    protected $description = 'Отправить модераторам утреннюю сводку новых жалоб';

    public function handle(): int
    {
        $reports = SiteReport::query()
            ->open()
            ->with(['site:id,title', 'reporter:id,name'])
            ->latest()
            ->get();

        if ($reports->isEmpty()) {
            $this->info('Новых жалоб нет.');

            return self::SUCCESS;
        }

        $moderators = User::query()
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user): bool => $user->hasDeliverableEmail()
                && $user->hasAccess('platform.marketplace.sites'));

        if ($moderators->isEmpty()) {
            $this->warn('Не найдено модераторов с доступом к объявлениям.');

            return self::SUCCESS;
        }

        // Команда может запуститься повторно — сводка уходит один раз в сутки.
        if (! Cache::add('notifications:reports-digest:'.today()->toDateString(), true, now()->endOfDay())) {
            $this->info('Сводка за сегодня уже отправлена.');

            return self::SUCCESS;
        }

        $entries = $reports
            ->take(self::MAX_REPORTS_IN_MAIL)
            ->map(fn (SiteReport $report): array => [
                'siteId' => $report->site_id,
                'siteTitle' => $report->site?->title ?? 'Объявление #'.$report->site_id,
                'reason' => $report->reason->label(),
                'reporter' => $report->reporter?->name,
                'url' => route('platform.marketplace.sites.edit', $report->site_id),
            ])
            ->values()
            ->all();

        foreach ($moderators as $moderator) {
            $moderator->notify(new ReportsDigest($entries, $reports->count()));
        }

        $this->info('Сводка отправлена модераторам: '.$moderators->count().'. Жалоб: '.$reports->count().'.');

        return self::SUCCESS;
    }
}
