<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Карта сайта для поисковиков.
 *
 * Отдаётся маршрутом, а не статическим файлом: список лотов меняется,
 * а кэш на час избавляет от повторных запросов к БД.
 */
class SitemapController extends Controller
{
    private const CACHE_KEY = 'sitemap.xml';

    private const CACHE_TTL_MINUTES = 60;

    public function __invoke(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function build(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($this->staticPages() as $page) {
            $lines[] = $this->url($page['loc'], $page['priority'], null);
        }

        Site::query()
            ->publiclyVisible()
            ->select(['id', 'title', 'updated_at'])
            ->orderByDesc('updated_at')
            ->chunk(500, function ($sites) use (&$lines): void {
                foreach ($sites as $site) {
                    $lines[] = $this->url(
                        url($site->canonicalPath()),
                        '0.8',
                        $site->updated_at?->toAtomString(),
                    );
                }
            });

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{loc: string, priority: string}>
     */
    private function staticPages(): array
    {
        return [
            ['loc' => url('/').'/', 'priority' => '1.0'],
            ['loc' => url('/how-it-works'), 'priority' => '0.5'],
            ['loc' => url('/pricing'), 'priority' => '0.6'],
        ];
    }

    private function url(string $loc, string $priority, ?string $lastModified): string
    {
        $parts = [
            '  <url>',
            '    <loc>'.e($loc).'</loc>',
        ];

        if ($lastModified !== null) {
            $parts[] = '    <lastmod>'.$lastModified.'</lastmod>';
        }

        $parts[] = '    <priority>'.$priority.'</priority>';
        $parts[] = '  </url>';

        return implode("\n", $parts);
    }
}
