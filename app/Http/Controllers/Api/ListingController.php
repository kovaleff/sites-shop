<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSiteResource;
use App\Models\Site;
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
    public function show(Request $request, string $slug): JsonResponse
    {
        $siteId = (int) Str::before($slug, '-');

        abort_if($siteId <= 0, 404);

        $site = Site::query()
            ->publiclyVisible()
            ->with('categories')
            ->findOrFail($siteId);

        return response()->json([
            'data' => (new PublicSiteResource($site))->resolve($request),
        ]);
    }
}
