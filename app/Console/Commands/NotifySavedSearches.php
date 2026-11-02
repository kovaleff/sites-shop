<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\SavedSearch;
use App\Notifications\SavedSearchMatches;
use Illuminate\Console\Command;

class NotifySavedSearches extends Command
{
    /**
     * @var string
     */
    protected $signature = 'saved-searches:notify';

    /**
     * @var string
     */
    protected $description = 'Отправить дайджесты новых лотов по сохранённым фильтрам';

    public function handle(): int
    {
        $sent = 0;

        SavedSearch::query()
            ->with('user')
            ->chunkById(100, function ($searches) use (&$sent): void {
                foreach ($searches as $search) {
                    if (! $search->isDue()) {
                        continue;
                    }

                    $user = $search->user;

                    if ($user === null) {
                        continue;
                    }

                    // Тип писем отключён: не копим очередь, но двигаем порог,
                    // чтобы после включения не пришёл разом весь накопленный список.
                    if (! $user->wantsNotification(NotificationType::SavedSearches)) {
                        $search->forceFill(['last_notified_at' => now()])->save();

                        continue;
                    }

                    $threshold = $search->last_notified_at ?? $search->created_at ?? now();

                    $sites = $search->matchingSites()
                        ->where('created_at', '>', $threshold)
                        ->reorder()
                        ->latest('created_at')
                        ->latest('id')
                        ->limit(20)
                        ->get();

                    if ($sites->isEmpty()) {
                        continue;
                    }

                    $user->notify(new SavedSearchMatches($search, $sites));

                    $search->forceFill(['last_notified_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Отправлено дайджестов по сохранённым фильтрам: {$sent}.");

        return self::SUCCESS;
    }
}
