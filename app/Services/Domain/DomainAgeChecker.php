<?php

namespace App\Services\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Возраст домена по данным RDAP (бесплатно, без ключа).
 *
 * Проверка выключена при `sites.verification.min_domain_age_days = 0`.
 * Если RDAP недоступен или дата регистрации не найдена — домен не блокируется.
 */
final class DomainAgeChecker
{
    private const ENDPOINT = 'https://rdap.org/domain/';

    public function isTooYoung(string $domain): bool
    {
        $minDays = (int) config('sites.verification.min_domain_age_days');

        if ($minDays <= 0) {
            return false;
        }

        try {
            $response = Http::connectTimeout((int) config('sites.verification.connect_timeout'))
                ->timeout((int) config('sites.verification.timeout'))
                ->acceptJson()
                ->get(self::ENDPOINT.rawurlencode($domain));
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        foreach ((array) $response->json('events', []) as $event) {
            if (($event['eventAction'] ?? null) !== 'registration') {
                continue;
            }

            $registeredAt = CarbonImmutable::parse((string) ($event['eventDate'] ?? ''));

            return $registeredAt->diffInDays(now()) < $minDays;
        }

        return false;
    }
}
