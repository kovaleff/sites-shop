<?php

namespace App\Models;

use App\Enums\ListingStatus;
use Database\Factories\SubscriptionSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Singleton-настройки тарифа подписки (id = 1).
 *
 * Значения читаются через кеш на 60 секунд, чтобы администратор видел
 * изменения не позднее минуты (SC-012), но кабинет не делал запрос к БД
 * на каждый рендер.
 */
class SubscriptionSetting extends Model
{
    /** @use HasFactory<SubscriptionSettingFactory> */
    use HasFactory;

    public const CACHE_KEY = 'subscription:settings';

    public const CACHE_TTL_SECONDS = 60;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'base_price' => 0,
        'periods' => '[1,3,6,12]',
        'discounts' => '{}',
        'target_status' => 'active',
        'qr_ttl_minutes' => 15,
        'is_active' => false,
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'base_price',
        'periods',
        'discounts',
        'target_status',
        'qr_ttl_minutes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'periods' => 'array',
            'discounts' => 'array',
            'qr_ttl_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Текущие настройки тарифа; при отсутствии строки возвращаются значения
     * по умолчанию (без создания записи).
     *
     * В кеш кладётся только массив атрибутов: кеширование Eloquent-модели
     * ломается на сериализующих хранилищах (database, redis, file), так как
     * Laravel по умолчанию не разрешает десериализацию классов и возвращает
     * `__PHP_Incomplete_Class`.
     */
    public static function current(): self
    {
        $payload = Cache::get(self::CACHE_KEY);

        if (! is_array($payload)) {
            $payload = self::cachePayload();
            Cache::put(self::CACHE_KEY, $payload, self::CACHE_TTL_SECONDS);
        }

        return self::fromCachePayload($payload);
    }

    /**
     * @return array{exists: bool, attributes: array<string, mixed>}
     */
    private static function cachePayload(): array
    {
        $settings = static::query()->find(1);

        return [
            'exists' => $settings !== null,
            'attributes' => $settings?->getAttributes() ?? static::defaults(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function fromCachePayload(array $payload): self
    {
        $attributes = $payload['attributes'] ?? null;

        if (! is_array($attributes)) {
            $attributes = static::defaults();
        }

        if (! ($payload['exists'] ?? false)) {
            return new self($attributes);
        }

        return (new self)->newFromBuilder($attributes);
    }

    /**
     * Значения по умолчанию: тариф выключен, пока администратор не задал цену.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'id' => 1,
            'base_price' => 0,
            'periods' => [1, 3, 6, 12],
            'discounts' => [],
            'target_status' => ListingStatus::Active->value,
            'qr_ttl_minutes' => 15,
            'is_active' => false,
        ];
    }

    /**
     * Создаёт строку настроек, если её ещё нет.
     */
    public static function ensureExists(): self
    {
        $settings = static::query()->firstOrCreate(
            ['id' => 1],
            self::defaults(),
        );

        Cache::forget(self::CACHE_KEY);

        return $settings;
    }

    /**
     * Целевой статус объявления после успешной оплаты.
     */
    public function targetListingStatus(): ListingStatus
    {
        return ListingStatus::tryFrom((string) $this->target_status) ?? ListingStatus::Active;
    }

    /**
     * Нормализованный набор сроков для API: месяцы + процент скидки.
     *
     * @return array<int, array{months: int, discountPercent: int}>
     */
    public function periodOptions(): array
    {
        $periods = collect($this->normalizedPeriods())
            ->map(fn (int $months): array => [
                'months' => $months,
                'discountPercent' => $this->discountFor($months),
            ])
            ->all();

        return array_values($periods);
    }

    /**
     * Процент скидки для срока; 0 для одного месяца и незаданных сроков.
     */
    public function discountFor(int $months): int
    {
        if ($months < 3) {
            return 0;
        }

        $discounts = $this->normalizedDiscounts();

        return (int) ($discounts[(string) $months] ?? $discounts[$months] ?? 0);
    }

    /**
     * @return array<int, int>
     */
    public function normalizedPeriods(): array
    {
        $periods = $this->periods;

        if (! is_array($periods)) {
            $periods = self::defaults()['periods'];
        }

        $normalized = collect($periods)
            ->map(fn ($months): int => (int) $months)
            ->filter(fn (int $months): bool => $months >= 1 && $months <= 24)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized === [] ? self::defaults()['periods'] : $normalized;
    }

    /**
     * @return array<string|int, int>
     */
    public function normalizedDiscounts(): array
    {
        $discounts = $this->discounts;

        if (! is_array($discounts)) {
            return [];
        }

        $normalized = [];

        foreach ($discounts as $months => $percent) {
            $months = (int) $months;
            $percent = (int) $percent;

            if ($months < 3 || $percent < 1 || $percent > 99) {
                continue;
            }

            $normalized[$months] = $percent;
        }

        return $normalized;
    }

    public function hasConfiguredPrice(): bool
    {
        return (float) $this->base_price > 0;
    }
}
