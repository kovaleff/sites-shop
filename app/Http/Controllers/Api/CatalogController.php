<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSiteResource;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    private const SORT_KEYS = ['newest', 'price_asc', 'price_desc', 'income_desc', 'income_asc'];

    private const SEARCH_MIN_LENGTH = 2;

    private const SEARCH_MAX_LENGTH = 100;

    /**
     * Публичный каталог с фильтрами контракта 003.
     */
    public function index(Request $request): JsonResponse
    {
        $priceMin = $this->nonNegativeInt($request->query('price_min'), 0);
        $priceMax = $this->nonNegativeInt($request->query('price_max'), 10_000_000);
        $incomeMin = $this->nonNegativeInt($request->query('income_min'), 0);
        $incomeMax = $this->nonNegativeInt($request->query('income_max'), 1_000_000);

        $zones = collect(explode(',', (string) $request->query('zones', '')))
            ->map(fn (string $zone): string => trim($zone))
            ->filter()
            ->values()
            ->all();

        $categories = collect(explode(',', (string) $request->query('categories', '')))
            ->map(fn (string $slug): string => Str::slug(trim($slug)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $search = $this->searchTerm($request->query('q'));
        $sort = $this->sortKey($request->query('sort'));

        $sites = Site::query()
            ->publiclyVisible()
            ->with('owner')
            ->when($priceMin > 0, fn ($query) => $query->where('price', '>=', $priceMin))
            ->when($priceMax < 10_000_000, fn ($query) => $query->where('price', '<=', $priceMax))
            ->when($incomeMin > 0, fn ($query) => $query->where('monthly_revenue', '>=', $incomeMin))
            ->when($incomeMax < 1_000_000, fn ($query) => $query->where('monthly_revenue', '<=', $incomeMax))
            ->when($zones !== [], fn ($query) => $query->whereIn('domain_zone', $zones))
            ->when($categories !== [], fn ($query) => $query->whereHas(
                'categories',
                fn ($categoriesQuery) => $categoriesQuery->whereIn('categories.slug', $categories),
            ))
            ->when($request->boolean('verified'), fn ($query) => $query->where('verified', true))
            ->when($search !== null, fn ($query) => $query->where(
                fn ($searchQuery) => $searchQuery
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('domain', 'like', '%'.$search.'%'),
            ))
            ->tap(fn ($query) => $this->applySort($query, $sort))
            ->paginate(25);

        return response()->json([
            'items' => PublicSiteResource::collection($sites->getCollection())->resolve($request),
            'meta' => $this->paginationMeta($sites),
        ]);
    }

    /**
     * Зоны публичных лотов: известные ru/com/de, затем по алфавиту.
     */
    public function zones(): JsonResponse
    {
        $counts = Site::query()
            ->publiclyVisible()
            ->whereNotNull('domain_zone')
            ->selectRaw('domain_zone, count(*) as aggregate')
            ->groupBy('domain_zone')
            ->pluck('aggregate', 'domain_zone');

        $known = ['ru', 'com', 'de'];

        $ordered = collect($known)
            ->filter(fn (string $zone): bool => $counts->has($zone))
            ->concat(
                $counts->keys()
                    ->reject(fn (string $zone): bool => in_array($zone, $known, true))
                    ->sort()
                    ->values()
            );

        return response()->json([
            'items' => $ordered
                ->map(fn (string $zone): array => [
                    'value' => $zone,
                    'label' => '.'.$zone,
                    'count' => (int) $counts[$zone],
                ])
                ->values(),
        ]);
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
