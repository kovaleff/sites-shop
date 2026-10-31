<?php

namespace App\Services\Domain;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Safe Browsing Lookup API.
 *
 * Без ключа проверка выключена. При сетевой ошибке домен считается безопасным:
 * недоступность внешнего сервиса не должна блокировать публикацию.
 */
final class GoogleSafeBrowsingChecker implements ReputationChecker
{
    private const ENDPOINT = 'https://safebrowsing.googleapis.com/v4/threatMatches:find';

    /**
     * @var array<int, string>
     */
    private const THREAT_TYPES = [
        'MALWARE',
        'SOCIAL_ENGINEERING',
        'UNWANTED_SOFTWARE',
        'POTENTIALLY_HARMFUL_APPLICATION',
    ];

    public function isUnsafe(string $domain): bool
    {
        $key = (string) config('sites.safe_browsing.key');

        if ($key === '') {
            return false;
        }

        try {
            $response = Http::connectTimeout((int) config('sites.verification.connect_timeout'))
                ->timeout((int) config('sites.verification.timeout'))
                ->post(self::ENDPOINT.'?key='.urlencode($key), [
                    'client' => [
                        'clientId' => 'sites-shop',
                        'clientVersion' => '1.0',
                    ],
                    'threatInfo' => [
                        'threatTypes' => self::THREAT_TYPES,
                        'platformTypes' => ['ANY_PLATFORM'],
                        'threatEntryTypes' => ['URL'],
                        'threatEntries' => [
                            ['url' => "http://{$domain}/"],
                        ],
                    ],
                ]);
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful()) {
            return false;
        }

        return $response->json('matches') !== null && $response->json('matches') !== [];
    }
}
