<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSiteResource;
use App\Models\Site;
use App\Services\Catalog\CatalogFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Публичный каталог с фильтрами контракта 003.
     */
    public function index(Request $request, CatalogFilter $catalogFilter): JsonResponse
    {
        $user = $request->user();

        $sites = Site::query()
            ->publiclyVisible()
            ->with('owner')
            ->when($user !== null, fn (Builder $query) => $query->withExists([
                'favorites as is_favorite' => fn (Builder $favoritesQuery) => $favoritesQuery->where('user_id', $user->id),
            ]));

        $catalogFilter->apply($sites, $catalogFilter->fromQuery($request->query()));

        $sites = $sites->paginate(Site::CATALOG_PER_PAGE);

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
}
