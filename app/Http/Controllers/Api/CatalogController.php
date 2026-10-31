<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
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

        $sites = Site::query()
            ->publiclyVisible()
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
            ->orderByDesc('created_at')
            ->orderBy('id')
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
}
