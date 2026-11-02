<?php

namespace App\Support\Seo;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Серверные метаданные страниц: заголовок, описание, canonical, Open Graph
 * и JSON-LD.
 *
 * SPA отдаёт эти данные только после исполнения JavaScript, а VK и Telegram
 * JS не исполняют вовсе. Поэтому метаданные формируются в Blade-шаблоне `app`.
 */
final class SeoMeta
{
    public const SITE_NAME = 'Sites-shop';

    private const DESCRIPTION_LIMIT = 160;

    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public ?string $image = null,
        public string $type = 'website',
        public bool $indexable = true,
        public ?array $jsonLd = null,
    ) {
        $this->title = Str::limit(trim($title), 120, '');
        $this->description = self::clip($description);
    }

    public static function forCatalog(): self
    {
        return new self(
            title: self::SITE_NAME.' — маркетплейс готовых сайтов',
            description: 'Каталог готовых сайтов на продажу: действующие проекты с доходом, '
                .'проверенными владельцами и прозрачной аналитикой. Фильтр по тематике, цене и доменной зоне.',
            canonical: url('/'),
        );
    }

    public static function forPage(string $title, string $description, string $path): self
    {
        return new self(
            title: $title.' — '.self::SITE_NAME,
            description: $description,
            canonical: url($path),
        );
    }

    /**
     * Страница лота: уникальные заголовок и описание, Open Graph и разметка Offer.
     *
     * @param  array<int, string>  $categories
     */
    public static function forListing(Site $site, array $categories = [], ?string $imageUrl = null): self
    {
        $price = self::money((float) $site->price).' ₽';
        $income = self::money((float) $site->monthly_revenue).' ₽ в месяц';

        $description = sprintf(
            '%s — сайт %s на продажу за %s. Доход %s. %s',
            $site->title,
            $site->domain,
            $price,
            $income,
            (string) $site->description,
        );

        return new self(
            title: $site->title.' — '.$site->domain.' за '.$price,
            description: $description,
            canonical: url($site->canonicalPath()),
            image: $imageUrl,
            type: 'article',
            jsonLd: self::listingJsonLd($site, $categories, $imageUrl),
        );
    }

    /**
     * Публичный профиль продавца: метатеги и разметка Person.
     */
    public static function forSeller(User $seller, int $publishedCount = 0): self
    {
        $bio = trim((string) $seller->bio);
        $description = $bio !== ''
            ? $bio
            : sprintf(
                'Профиль продавца %s на %s: опубликованных лотов — %d.',
                $seller->name,
                self::SITE_NAME,
                $publishedCount,
            );
        $image = self::absoluteImage($seller->avatar);

        return new self(
            title: $seller->name.' — продавец на '.self::SITE_NAME,
            description: $description,
            canonical: url('/seller/'.$seller->getKey()),
            image: $image,
            type: 'profile',
            jsonLd: self::sellerJsonLd($seller, $description, $image),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'image' => $this->image,
            'type' => $this->type,
            'indexable' => $this->indexable,
            'jsonLd' => $this->jsonLd,
        ];
    }

    /**
     * Описание режется до 160 символов: длиннее поисковики обрезают в сниппете.
     */
    public static function clip(string $text, int $limit = self::DESCRIPTION_LIMIT): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = rtrim(mb_substr($text, 0, $limit - 3), " \t\n\r.,;:—-");

        return $cut.'…';
    }

    /**
     * @param  array<int, string>  $categories
     * @return array<string, mixed>
     */
    private static function listingJsonLd(Site $site, array $categories, ?string $imageUrl): array
    {
        $url = url($site->canonicalPath());

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $site->title,
            'description' => self::clip((string) $site->description),
            'url' => $url,
            'sku' => 'site-'.$site->getKey(),
            'offers' => [
                '@type' => 'Offer',
                'price' => (string) (int) $site->price,
                'priceCurrency' => 'RUB',
                'availability' => 'https://schema.org/InStock',
                'url' => $url,
            ],
        ];

        if ($categories !== []) {
            $data['category'] = implode(', ', $categories);
        }

        if ($imageUrl !== null) {
            $data['image'] = $imageUrl;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function sellerJsonLd(User $seller, string $description, ?string $imageUrl): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $seller->name,
            'description' => self::clip($description),
            'url' => url('/seller/'.$seller->getKey()),
        ];

        if ($imageUrl !== null) {
            $data['image'] = $imageUrl;
        }

        return $data;
    }

    private static function absoluteImage(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ');
    }
}
