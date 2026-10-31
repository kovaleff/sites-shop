<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\Domain\DomainVerifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Подтверждает владение доменом и при успехе автоматически одобряет объявление.
 */
final class VerifySiteDomain implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(public readonly int $siteId) {}

    public function uniqueId(): string
    {
        return (string) $this->siteId;
    }

    public function handle(DomainVerifier $verifier): void
    {
        $site = Site::query()->find($this->siteId);

        if ($site === null) {
            return;
        }

        $result = $verifier->verify($site->domain, $site->verification_filename);

        if ($result->passed) {
            $site->markDomainVerified();

            return;
        }

        $site->markDomainVerificationFailed($result->reason ?? 'Не удалось проверить домен.');
    }
}
