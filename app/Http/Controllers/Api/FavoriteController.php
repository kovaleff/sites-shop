<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSiteResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Идентификаторы избранных лотов для состояния кнопок-сердечек.
     */
    public function ids(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->favorites()
                ->pluck('site_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all(),
        ]);
    }

    /**
     * Избранные лоты пользователя, включая проданные и снятые с публикации:
     * они остаются в списке, чтобы покупатель видел судьбу сохранённого лота.
     */
    public function index(Request $request): JsonResponse
    {
        $sites = $request->user()->favoriteSites()
            ->with(['categories', 'owner'])
            ->withExists([
                'favorites as is_favorite' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->orderByPivot('created_at', 'desc')
            ->paginate(Site::CATALOG_PER_PAGE);

        return response()->json([
            'data' => PublicSiteResource::collection($sites->getCollection())->resolve($request),
            'meta' => $this->paginationMeta($sites),
        ]);
    }

    public function store(Request $request, int $site): JsonResponse
    {
        $model = Site::query()->publiclyVisible()->findOrFail($site);

        $request->user()->favorites()->firstOrCreate([
            'site_id' => $model->getKey(),
        ]);

        return response()->json([
            'data' => [
                'siteId' => $model->getKey(),
                'isFavorite' => true,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $site): JsonResponse
    {
        $request->user()->favorites()
            ->where('site_id', $site)
            ->delete();

        return response()->json([
            'data' => [
                'siteId' => $site,
                'isFavorite' => false,
            ],
        ]);
    }
}
