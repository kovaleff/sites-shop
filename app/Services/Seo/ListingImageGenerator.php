<?php

namespace App\Services\Seo;

use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Картинка превью ссылки (og:image) для страницы лота.
 *
 * Собирается локально средствами GD из домена, цены и дохода: не зависит
 * от внешних сервисов скриншотов и доступна сразу после создания лота.
 * Результат кэшируется в storage/app/public/og и пересоздаётся при изменении лота.
 */
final class ListingImageGenerator
{
    public const DISK = 'public';

    public const DIRECTORY = 'og';

    private const WIDTH = 1200;

    private const HEIGHT = 630;

    /**
     * Цвета повторяют тёмную тему приложения (resources/css/app.css).
     */
    private const BACKGROUND = [0x19, 0x1A, 0x1C];

    private const ACCENT = [0xB9, 0xC9, 0x4A];

    private const FOREGROUND = [0xE8, 0xE9, 0xE2];

    private const MUTED = [0xA8, 0xA9, 0xA3];

    /**
     * @var array<int, string>
     */
    private const FONT_CANDIDATES = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
    ];

    /**
     * Путь к файлу внутри диска public или null, если сгенерировать не удалось.
     */
    public function forSite(Site $site): ?string
    {
        $path = $this->pathFor($site);

        if (Storage::disk(self::DISK)->exists($path)) {
            return $path;
        }

        $font = $this->fontPath();

        if ($font === null) {
            return null;
        }

        try {
            $image = $this->render($font, $site);
            Storage::disk(self::DISK)->put($path, $this->encode($image));
            imagedestroy($image);
        } catch (Throwable) {
            return null;
        }

        return $path;
    }

    /**
     * Абсолютный URL картинки лота или null.
     */
    public function urlFor(Site $site): ?string
    {
        $path = $this->forSite($site);

        if ($path === null) {
            return null;
        }

        return url(Storage::disk(self::DISK)->url($path));
    }

    /**
     * Сбрасывает кэш: вызывается при сохранении лота, чтобы превью не устаревало.
     */
    public function forget(Site $site): void
    {
        Storage::disk(self::DISK)->delete($this->pathFor($site));
    }

    private function pathFor(Site $site): string
    {
        return self::DIRECTORY.'/site-'.$site->getKey().'.png';
    }

    private function render(string $font, Site $site): \GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($image === false) {
            throw new \RuntimeException('Не удалось создать изображение.');
        }

        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...self::BACKGROUND));
        imagefilledrectangle(
            $image,
            0,
            0,
            self::WIDTH,
            10,
            (int) imagecolorallocate($image, ...self::ACCENT),
        );

        $this->writeBlock($image, $font, 44, self::FOREGROUND, 80, 120,
            $this->wrap($font, 44, (string) $site->title, self::WIDTH - 160, 2));

        $this->writeLine($image, $font, 30, self::ACCENT, 80, 340, (string) $site->domain);
        $this->writeLine($image, $font, 64, self::FOREGROUND, 80, 440, $this->money((float) $site->price).' ₽');
        $this->writeLine($image, $font, 30, self::MUTED, 80, 520,
            $this->money((float) $site->monthly_revenue).' ₽ в месяц');
        $this->writeLine($image, $font, 24, self::MUTED, 80, 590, 'sites-shop — маркетплейс готовых сайтов');

        return $image;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function writeBlock(\GdImage $image, string $font, int $size, array $rgb, int $x, int $y, array $lines): void
    {
        foreach ($lines as $index => $line) {
            $this->writeLine($image, $font, $size, $rgb, $x, $y + $index * (int) round($size * 1.35), $line);
        }
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function writeLine(\GdImage $image, string $font, int $size, array $rgb, int $x, int $y, string $text): void
    {
        imagettftext($image, $size, 0, $x, $y, (int) imagecolorallocate($image, ...$rgb), $font, $text);
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $font, int $size, string $text, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->textWidth($font, $size, $candidate) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' .,;:—-').'…';
        }

        return $lines === [] ? [''] : $lines;
    }

    private function textWidth(string $font, int $size, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        if ($box === false) {
            return 0;
        }

        return abs($box[2] - $box[0]);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, ',', ' ');
    }

    private function fontPath(): ?string
    {
        foreach (self::FONT_CANDIDATES as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function encode(\GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);
        $contents = (string) ob_get_clean();

        return $contents;
    }
}
