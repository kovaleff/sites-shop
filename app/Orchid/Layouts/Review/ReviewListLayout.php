<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Review;

use App\Enums\ModerationStatus;
use App\Models\Review;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ReviewListLayout extends Table
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
            TD::make('id', 'ID')
                ->sort()
                ->width('70px')
                ->render(fn (Review $review) => $review->id),

            TD::make('site', 'Объявление')
                ->cantHide()
                ->render(fn (Review $review) => $review->site === null
                    ? '—'
                    : Link::make($review->site->title.' ('.$review->site->domain.')')
                        ->route('platform.marketplace.sites.edit', $review->site)),

            TD::make('seller', 'Продавец')
                ->render(fn (Review $review) => $review->seller === null
                    ? '—'
                    : Link::make($review->seller->name)
                        ->route('platform.systems.users.edit', $review->seller)),

            TD::make('author', 'Автор отзыва')
                ->render(fn (Review $review) => $review->author === null
                    ? '—'
                    : Link::make($review->author->name)
                        ->route('platform.systems.users.edit', $review->author)),

            TD::make('rating', 'Оценка')
                ->sort()
                ->alignRight()
                ->filter(TD::FILTER_NUMBER_RANGE)
                ->render(fn (Review $review) => $review->rating.' / 5'),

            TD::make('comment', 'Комментарий')
                ->render(fn (Review $review) => Str::limit((string) $review->comment, 120) ?: '—'),

            TD::make('moderation_status', 'Модерация')
                ->filter(TD::FILTER_SELECT, collect(ModerationStatus::cases())
                    ->mapWithKeys(fn (ModerationStatus $status) => [$status->value => $status->label()])
                    ->all())
                ->render(fn (Review $review) => $review->moderation_status->label()),

            TD::make('created_at', 'Создан')
                ->sort()
                ->alignRight()
                ->filter(TD::FILTER_DATE_RANGE)
                ->render(fn (Review $review) => $review->created_at?->format('d.m.Y H:i')),

            TD::make('actions', 'Решение')
                ->alignRight()
                ->render(fn (Review $review) => $review->isPublished()
                    ? ''
                    : Button::make('Опубликовать')
                        ->icon('bs.check-circle')
                        ->method('approve')
                        ->parameters(['review' => $review->id])
                        ->class('btn btn-link text-success')),

            TD::make('dismiss', '')
                ->alignRight()
                ->render(fn (Review $review) => $review->isPublished()
                    ? ''
                    : Button::make('Отклонить')
                        ->icon('bs.x-circle')
                        ->method('reject')
                        ->parameters(['review' => $review->id])
                        ->class('btn btn-link text-danger')),
        ];
    }
}
