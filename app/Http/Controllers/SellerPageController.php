<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicSellerResource;
use App\Http\Resources\PublicSiteResource;
use App\Services\Sellers\SellerProfileService;
use App\Support\Seo\SeoMeta;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Серверная оболочка публичного профиля продавца с готовыми метатегами
 * и состоянием для гидратации SPA.
 */
class SellerPageController extends Controller
{
    public function __construct(private readonly SellerProfileService $profiles) {}

    public function __invoke(Request $request, int $seller): View
    {
        [$profile, $listings] = $this->profiles->load($seller, 1, $request->user());

        return view('app', [
            'meta' => SeoMeta::forSeller(
                $profile,
                (int) $profile->published_sites_count,
            )->toArray(),
            'initialState' => [
                'seller' => [
                    'data' => (new PublicSellerResource($profile))->resolve($request),
                    'listings' => PublicSiteResource::collection($listings->getCollection())->resolve($request),
                    'meta' => $this->paginationMeta($listings),
                ],
            ],
        ]);
    }
}
