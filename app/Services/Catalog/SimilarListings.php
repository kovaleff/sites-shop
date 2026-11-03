<?php

namespace App\Services\Catalog;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Подбор похожих лотов для карточки объявления.
 *
 * Приоритет подбора: та же тематика и цена в пределах ±30%, затем те же
 * тематики без ограничения цены и, если этого мало, ближайшие по цене лоты
 * других тематик. Доход используется как дополнительный признак близости.
 */
final class SimilarListings
{
    public const DEFAULT_LIMIT = 6;

    public const MAX_LIMIT = 12;

    private const PRICE_TOLERANCE = 0.3;

    /**
     * @return Collection<int, Site>
     */
    public function forSite(Site $site, ?User $viewer = null, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $site->loadMissing('categories');

        $categoryIds = $site->categories->modelKeys();
        $price = (float) $site->price;
        $income = (float) $site->monthly_revenue;

        /** @var Collection<int, Site> $matches */
        $matches = new Collection;

        foreach ($this->tiers($categoryIds, $price) as [$tierCategoryIds, $withPriceBand]) {
            if ($matches->count() >= $limit) {
                break;
            }

            $candidates = $this->query($site, $viewer, $matches->modelKeys())
                ->when($tierCategoryIds !== [], fn (Builder $query) => $query->whereHas(
                    'categories',
                    fn (Builder $categoriesQuery) => $categoriesQuery->whereIn('categories.id', $tierCategoryIds),
                ))
                ->when($withPriceBand, fn (Builder $query) => $this->applyPriceBand($query, $price))
                ->when($price > 0, fn (Builder $query) => $query->orderByRaw('ABS(price - ?)', [$price]))
                ->when($income > 0, fn (Builder $query) => $query->orderByRaw('ABS(monthly_revenue - ?)', [$income]))
                ->orderByDesc('verified')
                ->orderByDesc('created_at')
                ->orderBy('id')
                ->limit($limit - $matches->count())
                ->get();

            $matches = $matches->concat($candidates);
        }

        return $matches->take($limit)->values();
    }

    /**
     * Уровни подбора от самого точного к самому широкому.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, array{0: array<int, int>, 1: bool}>
     */
    private function tiers(array $categoryIds, float $price): array
    {
        $hasPrice = $price > 0;
        $tiers = [];

        if ($categoryIds !== []) {
            // Та же тематика и близкая цена, затем та же тематика без ценового коридора.
            $tiers[] = [$categoryIds, $hasPrice];
            $tiers[] = [$categoryIds, false];
        }

        if ($hasPrice) {
            // Тематики кончились — остаются лоты в близком ценовом диапазоне.
            $tiers[] = [[], true];
        }

        return $tiers;
    }

    /**
     * @param  array<int, int>  $excludeIds
     * @return Builder<Site>
     */
    private function query(Site $site, ?User $viewer, array $excludeIds = []): Builder
    {
        return Site::query()
            ->publiclyVisible()
            ->whereKeyNot(array_merge([$site->getKey()], $excludeIds))
            ->with(['categories', 'owner'])
            ->when($viewer !== null, fn (Builder $query) => $query->withExists([
                'favorites as is_favorite' => fn (Builder $favoritesQuery) => $favoritesQuery->where('user_id', $viewer->id),
            ]));
    }

    /**
     * @param  Builder<Site>  $query
     */
    private function applyPriceBand(Builder $query, float $price): void
    {
        $query->whereBetween('price', [
            $price * (1 - self::PRICE_TOLERANCE),
            $price * (1 + self::PRICE_TOLERANCE),
        ]);
    }
}
