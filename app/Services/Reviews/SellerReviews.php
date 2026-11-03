<?php

namespace App\Services\Reviews;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Публичная лента отзывов продавца.
 */
final class SellerReviews
{
    /**
     * @return LengthAwarePaginator<int, Review>
     */
    public function publishedFor(int $sellerId, int $page = 1): LengthAwarePaginator
    {
        return Review::query()
            ->published()
            ->forSeller($sellerId)
            ->with(['author', 'site'])
            ->newestFirst()
            ->paginate(Review::PER_PAGE, ['*'], 'page', max(1, $page));
    }
}
