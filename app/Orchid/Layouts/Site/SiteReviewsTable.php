<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Models\Review;
use Illuminate\Support\Str;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SiteReviewsTable extends Table
{
    /**
     * @var string
     */
    public $target = 'reviews';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('author', 'Автор')
                ->render(fn (Review $review) => $review->author?->name ?? '—'),

            TD::make('rating', 'Оценка')
                ->alignRight()
                ->render(fn (Review $review) => $review->rating.' / 5'),

            TD::make('comment', 'Комментарий')
                ->render(fn (Review $review) => Str::limit((string) $review->comment, 140) ?: '—'),

            TD::make('moderation_status', 'Модерация')
                ->render(fn (Review $review) => $review->moderation_status->label()),

            TD::make('created_at', 'Создан')
                ->alignRight()
                ->render(fn (Review $review) => $review->created_at?->format('d.m.Y H:i')),
        ];
    }
}
