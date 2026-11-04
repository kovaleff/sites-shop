<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\SubscriptionPayment;

use App\Enums\PaymentReviewDecision;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

/**
 * Форма решения по платежу «Требует проверки».
 */
class PaymentReviewLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Select::make('review.decision')
                ->title('Решение')
                ->options([
                    PaymentReviewDecision::Accepted->value => 'Принять платёж',
                    PaymentReviewDecision::Rejected->value => 'Отклонить платёж',
                ])
                ->required(),

            TextArea::make('review.comment')
                ->title('Комментарий')
                ->rows(4)
                ->placeholder('Кратко объясните решение (5–2000 символов)')
                ->required(),
        ];
    }
}
