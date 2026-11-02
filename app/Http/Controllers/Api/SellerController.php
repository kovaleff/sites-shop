<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSellerResource;
use App\Http\Resources\PublicSiteResource;
use App\Services\Sellers\SellerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    /**
     * Публичный профиль продавца и его опубликованные лоты.
     */
    public function show(Request $request, SellerProfileService $profiles, int $seller): JsonResponse
    {
        [$profile, $listings] = $profiles->load($seller, (int) $request->query('page', 1));

        return response()->json([
            'data' => (new PublicSellerResource($profile))->resolve($request),
            'listings' => PublicSiteResource::collection($listings->getCollection())->resolve($request),
            'meta' => $this->paginationMeta($listings),
        ]);
    }
}
