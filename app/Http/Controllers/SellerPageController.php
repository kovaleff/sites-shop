<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicSellerResource;
use App\Http\Resources\PublicSiteResource;
use App\Http\Resources\ReviewResource;
use App\Services\Reviews\SellerReviews;
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
    public function __construct(
        private readonly SellerProfileService $profiles,
        private readonly SellerReviews $reviews,
    ) {}

    public function __invoke(Request $request, int $seller): View
    {
        [$profile, $listings] = $this->profiles->load($seller, 1, $request->user());
        $reviews = $this->reviews->publishedFor($seller, 1);

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
                    'reviews' => ReviewResource::collection($reviews->getCollection())->resolve($request),
                    'reviewsMeta' => $this->paginationMeta($reviews),
                ],
            ],
        ]);
    }
}
