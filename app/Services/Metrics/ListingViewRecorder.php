<?php

namespace App\Services\Metrics;

use App\Models\Site;
use App\Models\SiteView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Учёт просмотров объявления с защитой от накрутки и ботов.
 *
 * Один посетитель учитывается не чаще одного раза в сутки на лот: ключ
 * дедупликации живёт в кэше до конца дня, а запись в `site_views` создаётся
 * или увеличивается только при первом просмотре. Поэтому обновление страницы
 * не пишет в БД повторно.
 */
class ListingViewRecorder
{
    /**
     * Фрагменты User-Agent известных краулеров, превью-ботов и утилит.
     *
     * @var array<int, string>
     */
    private const BOT_USER_AGENTS = [
        'googlebot',
        'bingbot',
        'yandexbot',
        'duckduckbot',
        'baiduspider',
        'applebot',
        'petalbot',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
        'dotbot',
        'twitterbot',
        'telegrambot',
        'slackbot',
        'discordbot',
        'linkedinbot',
        'pinterestbot',
        'facebookexternalhit',
        'vkshare',
        'whatsapp',
        'skypeuripreview',
        'headlesschrome',
        'phantomjs',
        'lighthouse',
        'pingdom',
        'uptimerobot',
        'gtmetrix',
        'crawler',
        'spider',
        'slurp',
        'archiver',
        'scraper',
        'python-requests',
        'curl/',
        'wget/',
        'go-http-client',
        'java/',
        'okhttp',
        'axios/',
    ];

    /**
     * Общие слова, которые встречаются в User-Agent ботов, но не у людей.
     */
    private const BOT_WORD_PATTERN = '/(?:^|[\s;(+\/])(?:bot|crawl|spider)(?:[\s;)\/]|$)/i';

    /**
     * Записать просмотр, если его нужно учитывать.
     */
    public function record(Request $request, Site $site): void
    {
        if (! $this->shouldCount($request, $site)) {
            return;
        }

        $visitor = $this->visitorId($request);

        if ($visitor === null) {
            return;
        }

        $date = today()->toDateString();
        $dedupeKey = sprintf('site-views:%d:%s:%s', $site->getKey(), $date, $visitor);

        if (! Cache::add($dedupeKey, true, now()->endOfDay())) {
            return;
        }

        SiteView::query()->incrementOrCreate([
            'site_id' => $site->getKey(),
            'date' => $date,
        ]);
    }

    /**
     * Не считаем владельца лота, ботов, превью соцсетей и предзагрузку браузера.
     */
    private function shouldCount(Request $request, Site $site): bool
    {
        if (! config('sites.views.enabled', true)) {
            return false;
        }

        if ($request->user()?->getKey() === $site->user_id) {
            return false;
        }

        if ($this->isPrefetch($request)) {
            return false;
        }

        return ! $this->isBot($request->userAgent());
    }

    private function isPrefetch(Request $request): bool
    {
        $purpose = Str::lower(
            trim((string) $request->header('Sec-Purpose').' '.(string) $request->header('Purpose'))
        );

        return str_contains($purpose, 'prefetch')
            || str_contains($purpose, 'prerender')
            || str_contains($purpose, 'preview');
    }

    private function isBot(?string $userAgent): bool
    {
        $userAgent = Str::lower(trim((string) $userAgent));

        if ($userAgent === '') {
            return true;
        }

        foreach (self::BOT_USER_AGENTS as $fragment) {
            if (str_contains($userAgent, $fragment)) {
                return true;
            }
        }

        return (bool) preg_match(self::BOT_WORD_PATTERN, $userAgent);
    }

    /**
     * Стабильный идентификатор посетителя в рамках сессии.
     */
    private function visitorId(Request $request): ?string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        if (is_string($sessionId) && $sessionId !== '') {
            return hash('sha256', $sessionId);
        }

        $ip = $request->ip();

        if ($ip === null) {
            return null;
        }

        return hash('sha256', $ip.'|'.(string) $request->userAgent());
    }
}
