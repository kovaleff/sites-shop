<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\SubscriptionPayment;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class SubscriptionPaymentShowLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('payment.site')
                ->title('Объявление')
                ->readonly(),

            Input::make('payment.payer')
                ->title('Плательщик')
                ->readonly(),

            Input::make('payment.amount')
                ->title('Сумма')
                ->readonly(),

            Input::make('payment.currency')
                ->title('Валюта')
                ->readonly(),

            Input::make('payment.period_months')
                ->title('Период, мес.')
                ->readonly(),

            Input::make('payment.status')
                ->title('Статус')
                ->readonly(),

            Input::make('payment.paid_at')
                ->title('Оплачен')
                ->readonly(),

            Input::make('payment.external_id')
                ->title('Внешний идентификатор')
                ->readonly(),

            Input::make('payment.paid_until')
                ->title('Оплачено до')
                ->readonly(),
        ];
    }
}
