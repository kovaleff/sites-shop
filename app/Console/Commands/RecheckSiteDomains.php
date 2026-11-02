<?php

namespace App\Console\Commands;

use App\Enums\ModerationStatus;
use App\Models\Site;
use App\Services\Domain\DomainVerifier;
use Illuminate\Console\Command;

class RecheckSiteDomains extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sites:recheck-domains';

    /**
     * @var string
     */
    protected $description = 'Перепроверить живость и репутацию доменов опубликованных объявлений';

    public function handle(DomainVerifier $verifier): int
    {
        $checked = 0;
        $rejected = 0;

        Site::query()
            ->publiclyVisible()
            ->select(['id', 'user_id', 'domain', 'title', 'moderation_status', 'moderation_note', 'verification_error'])
            ->chunkById(100, function ($sites) use ($verifier, &$checked, &$rejected): void {
                foreach ($sites as $site) {
                    $checked++;

                    $result = $verifier->checkAvailability($site->domain);
                    $site->verification_checked_at = now();

                    if ($result->passed) {
                        // Не трогаем карту сайта, если содержимое объявления не изменилось.
                        if ($site->verification_error === null) {
                            $site->saveQuietly();
                        } else {
                            $site->verification_error = null;
                            $site->save();
                        }

                        continue;
                    }

                    $rejected++;
                    $site->verification_error = $result->reason;
                    $site->saveQuietly();

                    $site->setModeration(
                        ModerationStatus::Rejected,
                        'Домен недоступен: '.$result->reason,
                    );
                }
            });

        $this->info("Проверено доменов: {$checked}. Отклонено объявлений: {$rejected}.");

        return self::SUCCESS;
    }
}
