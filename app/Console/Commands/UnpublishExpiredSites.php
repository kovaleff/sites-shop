<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Models\Site;
use Illuminate\Console\Command;

class UnpublishExpiredSites extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sites:unpublish-expired';

    /**
     * @var string
     */
    protected $description = 'Снять с публикации объявления с истёкшей подпиской и сохранить причину';

    public function handle(): int
    {
        $unpublished = 0;

        Site::query()
            ->where('status', ListingStatus::Active)
            ->whereNotNull('paid_until')
            ->whereDate('paid_until', '<', today()->toDateString())
            ->chunkById(100, function ($sites) use (&$unpublished): void {
                foreach ($sites as $site) {
                    if ($site->unpublishForExpiredSubscription()) {
                        $unpublished++;
                    }
                }
            });

        $this->info("Снято с публикации объявлений: {$unpublished}.");

        return self::SUCCESS;
    }
}
