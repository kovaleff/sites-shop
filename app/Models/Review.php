<?php

namespace App\Models;

use App\Enums\ModerationStatus;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Filters\Types\WhereIn;
use Orchid\Filters\Types\WhereMaxMin;

/**
 * Отзыв покупателя о проданном лоте и его продавце.
 *
 * Публикуется только после проверки модератором: до этого отзыв не участвует
 * в рейтинге и не отдаётся в публичном API.
 */
#[Fillable(['site_id', 'author_id', 'seller_id', 'rating', 'comment'])]
class Review extends Model
{
    use Filterable;

    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    public const MIN_RATING = 1;

    public const MAX_RATING = 5;

    public const COMMENT_MAX = 1000;

    public const PER_PAGE = 10;

    /**
     * @var array<string, class-string>
     */
    protected $allowedFilters = [
        'moderation_status' => WhereIn::class,
        'rating' => WhereMaxMin::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * @var array<int, string>
     */
    protected $allowedSorts = [
        'id',
        'rating',
        'created_at',
        'published_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'moderation_status' => 'pending',
    ];

    protected static function booted(): void
    {
        // Рейтинг продавца пересчитывается при любом изменении отзыва,
        // чтобы агрегат в users всегда совпадал с опубликованными отзывами.
        static::saved(function (Review $review): void {
            $review->seller?->recalculateRating();
        });

        static::deleted(function (Review $review): void {
            $review->seller?->recalculateRating();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'moderation_status' => ModerationStatus::class,
            'moderated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Опубликованные отзывы: единственные, которые видят покупатели
     * и которые участвуют в рейтинге продавца.
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('moderation_status', ModerationStatus::Approved)
            ->whereNotNull('published_at');
    }

    #[Scope]
    protected function forSeller(Builder $query, int $sellerId): void
    {
        $query->where('seller_id', $sellerId);
    }

    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->latest('published_at')->latest('id');
    }

    public function isPublished(): bool
    {
        return $this->moderation_status === ModerationStatus::Approved
            && $this->published_at !== null;
    }

    public function approve(?string $note = null): void
    {
        $this->moderation_status = ModerationStatus::Approved;
        $this->moderation_note = $note;
        $this->moderated_at = now();
        $this->published_at ??= now();
        $this->save();
    }

    public function reject(?string $note = null): void
    {
        $this->moderation_status = ModerationStatus::Rejected;
        $this->moderation_note = $note;
        $this->moderated_at = now();
        $this->published_at = null;
        $this->save();
    }
}
