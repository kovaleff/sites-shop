<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\SubscriptionPayment;

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
            TD::make('id', 'Счёт')
                ->sort()
                ->render(fn (SubscriptionPayment $payment) => Link::make('№'.$payment->id)
                    ->route('platform.marketplace.payments.show', $payment)),

            TD::make('site_domain', 'Домен в счёте')
                ->cantHide()
                ->render(function (SubscriptionPayment $payment): string {
                    $current = $payment->site?->domain;
                    $snapshot = $payment->site_domain ?? $current ?? '—';

                    if ($current !== null && $snapshot !== $current) {
                        return $snapshot.' → '.$current;
                    }

                    return $snapshot;
                }),

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
                ->render(fn (SubscriptionPayment $payment) => $payment->status->label()),

            TD::make('payment_method', 'Способ')
                ->render(fn (SubscriptionPayment $payment) => $payment->payment_method?->label() ?? '—'),

            TD::make('is_test', 'Тест')
                ->alignCenter()
                ->render(fn (SubscriptionPayment $payment) => $payment->is_test ? 'Тест' : ''),

            TD::make('created_at', 'Создан')
                ->sort()
                ->render(fn (SubscriptionPayment $payment) => $payment->created_at?->format('d.m.Y H:i') ?? '—'),

            TD::make('paid_at', 'Оплачен')
                ->sort()
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => $payment->paid_at?->format('d.m.Y H:i') ?? '—'),
        ];
    }
}
