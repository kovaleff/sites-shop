<?php

namespace App\Services\Sellers;

use App\Enums\ListingStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Загружает публичный профиль продавца и его опубликованные лоты
 * для API и серверной страницы SPA.
 */
final class SellerProfileService
{
    /**
     * @return array{0: User, 1: LengthAwarePaginator<int, Site>}
     */
    public function load(int $sellerId, int $page = 1): array
    {
        $seller = User::query()->findOrFail($sellerId);

        $seller->loadCount([
            'sites as published_sites_count' => fn (Builder $query) => $query->publiclyVisible(),
            'sites as sold_sites_count' => fn (Builder $query) => $query->where('status', ListingStatus::Sold),
            'sites as verified_sites_count' => fn (Builder $query) => $query->where('verified', true),
        ]);

        $listings = $seller->sites()
            ->publiclyVisible()
            ->with(['categories', 'owner'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(Site::CATALOG_PER_PAGE, ['*'], 'page', max(1, $page));

        return [$seller, $listings];
    }
}
