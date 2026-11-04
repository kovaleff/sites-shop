<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\SubscriptionPayment;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class SubscriptionPaymentShowLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('payment.invoice_number')
                ->title('Номер счёта')
                ->readonly(),

            Input::make('payment.site_domain')
                ->title('Домен на момент счёта')
                ->readonly(),

            Input::make('payment.current_site_domain')
                ->title('Текущий домен объявления')
                ->readonly(),

            Input::make('payment.site')
                ->title('Объявление')
                ->readonly(),

            Input::make('payment.payer')
                ->title('Плательщик')
                ->readonly(),

            Input::make('payment.receipt_email')
                ->title('Email для чека')
                ->readonly(),

            Input::make('payment.base_price')
                ->title('Базовая цена за месяц')
                ->readonly(),

            Input::make('payment.discount')
                ->title('Скидка')
                ->readonly(),

            Input::make('payment.amount')
                ->title('Итого')
                ->readonly(),

            Input::make('payment.currency')
                ->title('Валюта')
                ->readonly(),

            Input::make('payment.period_months')
                ->title('Период, мес.')
                ->readonly(),

            Input::make('payment.target_status')
                ->title('Целевой статус после оплаты')
                ->readonly(),

            Input::make('payment.status')
                ->title('Статус')
                ->readonly(),

            Input::make('payment.payment_method')
                ->title('Способ оплаты')
                ->readonly(),

            Input::make('payment.is_test')
                ->title('Тестовая оплата')
                ->readonly(),

            Input::make('payment.created_at')
                ->title('Создан')
                ->readonly(),

            Input::make('payment.paid_at')
                ->title('Оплачен')
                ->readonly(),

            Input::make('payment.qr_expires_at')
                ->title('QR действует до')
                ->readonly(),

            Input::make('payment.external_id')
                ->title('Внешний идентификатор')
                ->readonly(),

            Input::make('payment.provider')
                ->title('Провайдер')
                ->readonly(),

            Input::make('payment.paid_until')
                ->title('Оплачено до')
                ->readonly(),

            Input::make('payment.failure_reason')
                ->title('Причина отказа/отмены')
                ->readonly(),

            Input::make('payment.review_reason')
                ->title('Причина проверки')
                ->readonly(),

            TextArea::make('payment.provider_payload')
                ->title('Данные провайдера (без подписи)')
                ->rows(4)
                ->readonly(),
        ];
    }
}
