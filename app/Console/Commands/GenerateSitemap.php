<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * @var string
     */
    protected $description = 'Пересобрать карту сайта и обновить её кэш';

    public function handle(SitemapController $sitemap): int
    {
        $xml = $sitemap->warm();

        $this->info('Карта сайта обновлена: '.substr_count($xml, '<url>').' URL.');

        return self::SUCCESS;
    }
}
