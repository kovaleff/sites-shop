<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\SubscriptionPayment;

use App\Enums\PaymentStatus;
use App\Models\SubscriptionPayment;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SubscriptionPaymentListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'payments';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->sort()
                ->render(fn (SubscriptionPayment $payment) => Link::make((string) $payment->id)
                    ->route('platform.marketplace.payments.show', $payment)),

            TD::make('site', 'Объявление')
                ->cantHide()
                ->render(fn (SubscriptionPayment $payment) => $payment->site?->domain ?? '—'),

            TD::make('payer', 'Плательщик')
                ->render(fn (SubscriptionPayment $payment) => $payment->payer?->name ?? '—'),

            TD::make('amount', 'Сумма')
                ->sort()
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => number_format((float) $payment->amount, 2, '.', ' ')),

            TD::make('currency', 'Валюта')
                ->render(fn (SubscriptionPayment $payment) => $payment->currency),

            TD::make('period_months', 'Период, мес.')
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => $payment->period_months),

            TD::make('status', 'Статус')
                ->filter(TD::FILTER_SELECT, collect(PaymentStatus::cases())
                    ->mapWithKeys(fn (PaymentStatus $status) => [$status->value => $status->label()])
                    ->all())
                ->render(fn (SubscriptionPayment $payment) => $payment->status->label()),

            TD::make('paid_at', 'Оплачен')
                ->sort()
                ->alignRight()
                ->filter(TD::FILTER_DATE_RANGE)
                ->render(fn (SubscriptionPayment $payment) => $payment->paid_at?->format('d.m.Y H:i') ?? '—'),
        ];
    }
}
