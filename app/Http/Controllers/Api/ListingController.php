<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSiteResource;
use App\Models\Site;
use App\Services\Metrics\ListingViewRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Публичная карточка лота для клиентской навигации внутри SPA.
 *
 * Серверный HTML отдаёт ListingPageController; этот эндпоинт нужен, когда
 * пользователь переходит на страницу лота уже внутри приложения.
 */
class ListingController extends Controller
{
    public function __construct(private readonly ListingViewRecorder $views) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $siteId = (int) Str::before($slug, '-');

        abort_if($siteId <= 0, 404);

        $site = Site::query()
            ->publiclyVisible()
            ->with(['categories', 'owner'])
            ->when($request->user() !== null, fn (Builder $query) => $query->withExists([
                'favorites as is_favorite' => fn (Builder $favoritesQuery) => $favoritesQuery->where('user_id', $request->user()->id),
            ]))
            ->findOrFail($siteId);

        $this->views->record($request, $site);

        return response()->json([
            'data' => (new PublicSiteResource($site))->resolve($request),
        ]);
    }
}
