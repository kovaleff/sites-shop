<?php

namespace App\Services\Domain;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Проверяет, что по адресу объявления действительно находится живой сайт,
 * которым владеет продавец: домен резолвится, главная страница отвечает,
 * а файл подтверждения доступен и не является HTML-заглушкой.
 */
final class DomainVerifier
{
    /**
     * Признаки парковки или страницы продажи домена.
     *
     * @var array<int, string>
     */
    private const PARKED_MARKERS = [
        'domain is for sale',
        'this domain is parked',
        'купить этот домен',
        'домен продаётся',
        'sedoparking',
        'parkingcrew',
    ];

    public function __construct(
        private readonly DomainResolver $resolver,
        private readonly ReputationChecker $reputation,
        private readonly DomainAgeChecker $age,
    ) {}

    public function verify(string $domain, string $verificationFilename): DomainVerificationResult
    {
        $domainCheck = $this->checkDomain($domain);

        if (! $domainCheck->passed) {
            return $domainCheck;
        }

        if ($this->age->isTooYoung($domain)) {
            return DomainVerificationResult::failed('Домен зарегистрирован слишком недавно.');
        }

        if (! $this->homepageResponds($domain)) {
            return DomainVerificationResult::failed('Сайт недоступен или на нём нет содержимого.');
        }

        if (! $this->verificationFileExists($domain, $verificationFilename)) {
            return DomainVerificationResult::failed('Файл подтверждения не найден на сайте.');
        }

        return DomainVerificationResult::passed();
    }

    /**
     * Проверка живости домена без файла подтверждения: используется
     * периодической перепроверкой опубликованных объявлений.
     */
    public function checkAvailability(string $domain): DomainVerificationResult
    {
        $domainCheck = $this->checkDomain($domain);

        if (! $domainCheck->passed) {
            return $domainCheck;
        }

        if (! $this->homepageResponds($domain)) {
            return DomainVerificationResult::failed('Сайт недоступен или на нём нет содержимого.');
        }

        return DomainVerificationResult::passed();
    }

    private function checkDomain(string $domain): DomainVerificationResult
    {
        if (! $this->resolver->resolves($domain)) {
            return DomainVerificationResult::failed('Домен не найден в DNS.');
        }

        if ($this->reputation->isUnsafe($domain)) {
            return DomainVerificationResult::failed('Домен отмечен как небезопасный.');
        }

        return DomainVerificationResult::passed();
    }

    private function homepageResponds(string $domain): bool
    {
        foreach (['https', 'http'] as $scheme) {
            $response = $this->get("{$scheme}://{$domain}/");

            if ($response === null) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            if (Str::contains(Str::lower($response->body()), self::PARKED_MARKERS)) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function verificationFileExists(string $domain, string $filename): bool
    {
        foreach (['https', 'http'] as $scheme) {
            $response = $this->get("{$scheme}://{$domain}/".rawurlencode($filename));

            if ($response === null || ! $response->successful()) {
                continue;
            }

            if ($this->isHtml($response)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function get(string $url): ?Response
    {
        try {
            return Http::connectTimeout((int) config('sites.verification.connect_timeout'))
                ->timeout((int) config('sites.verification.timeout'))
                ->withHeaders(['User-Agent' => 'SitesShopBot/1.0 (+verification)'])
                ->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    private function isHtml(Response $response): bool
    {
        if (Str::contains(Str::lower((string) $response->header('Content-Type')), 'text/html')) {
            return true;
        }

        return Str::contains(Str::lower($response->body()), '<html');
    }
}
