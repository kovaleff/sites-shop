<?php

namespace App\Services\Reviews;

use App\Enums\ListingStatus;
use App\Models\Site;
use App\Models\User;

/**
 * Правила доступа к написанию отзыва.
 *
 * Отзыв — сигнал доверия, поэтому оставить его может только реальный участник
 * сделки: покупатель, который вёл переписку по лоту, после того как лот продан.
 * Дополнительно отсекаются свежезарегистрированные аккаунты (антинакрутка).
 */
final class ReviewEligibility
{
    public const MIN_ACCOUNT_AGE_DAYS = 3;

    /**
     * Причина отказа или null, если отзыв можно оставить.
     */
    public function failureFor(User $author, Site $site): ?string
    {
        if ($site->isOwnedBy($author)) {
            return 'Нельзя оставить отзыв о собственном объявлении.';
        }

        if ($site->status !== ListingStatus::Sold) {
            return 'Отзыв можно оставить только после продажи лота.';
        }

        if ($this->accountIsTooNew($author)) {
            return sprintf(
                'Оставлять отзывы могут аккаунты старше %d дней.',
                self::MIN_ACCOUNT_AGE_DAYS,
            );
        }

        if (! $this->isBuyerOf($author, $site)) {
            return 'Оставить отзыв может только покупатель, который вёл переписку по этому лоту.';
        }

        if ($this->alreadyReviewed($author, $site)) {
            return 'Вы уже оставили отзыв по этому лоту.';
        }

        return null;
    }

    public function canReview(User $author, Site $site): bool
    {
        return $this->failureFor($author, $site) === null;
    }

    private function accountIsTooNew(User $author): bool
    {
        return $author->created_at === null
            || $author->created_at->greaterThan(now()->subDays(self::MIN_ACCOUNT_AGE_DAYS));
    }

    private function isBuyerOf(User $author, Site $site): bool
    {
        return $site->conversations()
            ->where('buyer_id', $author->getKey())
            ->exists();
    }

    private function alreadyReviewed(User $author, Site $site): bool
    {
        return $site->reviews()
            ->where('author_id', $author->getKey())
            ->exists();
    }
}
