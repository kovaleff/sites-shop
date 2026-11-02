<?php

namespace App\Services\Catalog;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Единая нормализация и применение фильтров каталога.
 *
 * Используется публичным каталогом и сохранёнными поисками, чтобы письма
 * о новых лотах находили ровно то же, что пользователь видит на сайте.
 */
final class CatalogFilter
{
    public const SORT_KEYS = ['newest', 'price_asc', 'price_desc', 'income_desc', 'income_asc'];

    public const PRICE_MAX = 10_000_000;

    public const INCOME_MAX = 1_000_000;

    private const SEARCH_MIN_LENGTH = 2;

    private const SEARCH_MAX_LENGTH = 100;

    /**
     * @param  array<string, mixed>  $query
     * @return array{price_min: int, price_max: int, income_min: int, income_max: int, zones: array<int, string>, categories: array<int, string>, verified: bool, q: string|null, sort: string}
     */
    public function fromQuery(array $query): array
    {
        return $this->normalize($query);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{price_min: int, price_max: int, income_min: int, income_max: int, zones: array<int, string>, categories: array<int, string>, verified: bool, q: string|null, sort: string}
     */
    public function normalize(array $input): array
    {
        return array_replace($this->defaults(), [
            'price_min' => $this->nonNegativeInt($input['price_min'] ?? null, 0),
            'price_max' => $this->nonNegativeInt($input['price_max'] ?? null, self::PRICE_MAX),
            'income_min' => $this->nonNegativeInt($input['income_min'] ?? null, 0),
            'income_max' => $this->nonNegativeInt($input['income_max'] ?? null, self::INCOME_MAX),
            'zones' => $this->zones($input['zones'] ?? null),
            'categories' => $this->categories($input['categories'] ?? null),
            'verified' => $this->boolean($input['verified'] ?? false),
            'q' => $this->searchTerm($input['q'] ?? null),
            'sort' => $this->sortKey($input['sort'] ?? null),
        ]);
    }

    /**
     * @return array{price_min: int, price_max: int, income_min: int, income_max: int, zones: array<int, string>, categories: array<int, string>, verified: bool, q: string|null, sort: string}
     */
    public function defaults(): array
    {
        return [
            'price_min' => 0,
            'price_max' => self::PRICE_MAX,
            'income_min' => 0,
            'income_max' => self::INCOME_MAX,
            'zones' => [],
            'categories' => [],
            'verified' => false,
            'q' => null,
            'sort' => 'newest',
        ];
    }

    /**
     * @param  Builder<Site>  $query
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): void
    {
        $filters = array_replace($this->defaults(), $filters);

        $query
            ->when($filters['price_min'] > 0, fn (Builder $query) => $query->where('price', '>=', $filters['price_min']))
            ->when($filters['price_max'] < self::PRICE_MAX, fn (Builder $query) => $query->where('price', '<=', $filters['price_max']))
            ->when($filters['income_min'] > 0, fn (Builder $query) => $query->where('monthly_revenue', '>=', $filters['income_min']))
            ->when($filters['income_max'] < self::INCOME_MAX, fn (Builder $query) => $query->where('monthly_revenue', '<=', $filters['income_max']))
            ->when($filters['zones'] !== [], fn (Builder $query) => $query->whereIn('domain_zone', $filters['zones']))
            ->when($filters['categories'] !== [], fn (Builder $query) => $query->whereHas(
                'categories',
                fn (Builder $categoriesQuery) => $categoriesQuery->whereIn('categories.slug', $filters['categories']),
            ))
            ->when($filters['verified'], fn (Builder $query) => $query->where('verified', true))
            ->when($filters['q'] !== null, fn (Builder $query) => $query->where(
                fn (Builder $searchQuery) => $searchQuery
                    ->where('title', 'like', '%'.$filters['q'].'%')
                    ->orWhere('domain', 'like', '%'.$filters['q'].'%'),
            ));

        $this->applySort($query, $filters['sort']);
    }

    /**
     * Query-параметры для ссылки «открыть в каталоге».
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function toQueryParameters(array $filters): array
    {
        $filters = array_replace($this->defaults(), $filters);
        $query = [];

        if ($filters['price_min'] > 0) {
            $query['price_min'] = (string) $filters['price_min'];
        }

        if ($filters['price_max'] < self::PRICE_MAX) {
            $query['price_max'] = (string) $filters['price_max'];
        }

        if ($filters['income_min'] > 0) {
            $query['income_min'] = (string) $filters['income_min'];
        }

        if ($filters['income_max'] < self::INCOME_MAX) {
            $query['income_max'] = (string) $filters['income_max'];
        }

        if ($filters['zones'] !== []) {
            $query['zones'] = implode(',', $filters['zones']);
        }

        if ($filters['categories'] !== []) {
            $query['categories'] = implode(',', $filters['categories']);
        }

        if ($filters['verified']) {
            $query['verified'] = '1';
        }

        if ($filters['q'] !== null) {
            $query['q'] = $filters['q'];
        }

        if ($filters['sort'] !== 'newest') {
            $query['sort'] = $filters['sort'];
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function toQueryString(array $filters): string
    {
        return http_build_query($this->toQueryParameters($filters), '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Невалидное или отрицательное значение заменяется значением по умолчанию.
     */
    private function nonNegativeInt(mixed $value, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max(0, (int) $value);
    }

    /**
     * @return array<int, string>
     */
    private function zones(mixed $value): array
    {
        return collect($this->listValues($value))
            ->map(fn (string $zone): string => trim($zone))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function categories(mixed $value): array
    {
        return collect($this->listValues($value))
            ->map(fn (string $slug): string => Str::slug(trim($slug)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function listValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): string => (string) $item, $value);
        }

        if (! is_string($value)) {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    private function boolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * Поисковый запрос короче двух символов не применяется.
     */
    private function searchTerm(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $term = trim($value);

        if (mb_strlen($term) < self::SEARCH_MIN_LENGTH) {
            return null;
        }

        return mb_substr($term, 0, self::SEARCH_MAX_LENGTH);
    }

    /**
     * Неизвестный порядок сортировки заменяется порядком по умолчанию.
     */
    private function sortKey(mixed $value): string
    {
        return is_string($value) && in_array($value, self::SORT_KEYS, true) ? $value : 'newest';
    }

    /**
     * @param  Builder<Site>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderBy('id'),
            'income_asc' => $query->orderBy('monthly_revenue')->orderBy('id'),
            'income_desc' => $query->orderByDesc('monthly_revenue')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderBy('id'),
        };
    }
}
