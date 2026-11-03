<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Review;

use App\Enums\ModerationStatus;
use App\Models\Review;
use App\Orchid\Layouts\Review\ReviewListLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class ReviewListScreen extends Screen
{
    /**
     * Все отзывы покупателей с фильтрами по оценке, статусу и дате.
     *
     * @return array<string, mixed>
     */
    public function query(): iterable
    {
        return [
            'reviews' => Review::query()
                ->with(['site', 'author', 'seller'])
                ->filters()
                ->defaultSort('id', 'desc')
                ->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Отзывы';
    }

    public function description(): ?string
    {
        return 'Отзывы покупателей о проданных лотах: публикация и отклонение.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.reviews',
        ];
    }

    /**
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Опубликовать все на проверке')
                ->icon('bs.check-all')
                ->method('approveAll')
                ->confirm('Опубликовать все отзывы, ожидающие проверки?'),
        ];
    }

    /**
     * @return string[]|Layout[]
     */
    public function layout(): iterable
    {
        return [
            ReviewListLayout::class,
        ];
    }

    public function approve(Request $request): void
    {
        $review = Review::query()->find((int) $request->input('review'));

        if ($review === null) {
            Toast::error('Отзыв не найден.');

            return;
        }

        $review->approve();

        Toast::info('Отзыв опубликован.');
    }

    public function reject(Request $request): void
    {
        $review = Review::query()->find((int) $request->input('review'));

        if ($review === null) {
            Toast::error('Отзыв не найден.');

            return;
        }

        $review->reject('Отклонено модератором.');

        Toast::warning('Отзыв отклонён.');
    }

    public function approveAll(): void
    {
        $reviews = Review::query()
            ->where('moderation_status', ModerationStatus::Pending)
            ->limit(100)
            ->get();

        $reviews->each(fn (Review $review) => $review->approve());

        Toast::info('Опубликовано отзывов: '.$reviews->count().'.');
    }
}
