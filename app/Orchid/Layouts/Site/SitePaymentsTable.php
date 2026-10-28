<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Models\SubscriptionPayment;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SitePaymentsTable extends Table
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
                ->render(fn (SubscriptionPayment $payment) => Link::make((string) $payment->id)
                    ->route('platform.marketplace.payments.show', $payment)),

            TD::make('amount', 'Сумма')
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => number_format((float) $payment->amount, 2, '.', ' ')),

            TD::make('period_months', 'Период, мес.')
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => $payment->period_months),

            TD::make('status', 'Статус')
                ->render(fn (SubscriptionPayment $payment) => $payment->status->label()),

            TD::make('paid_at', 'Оплачен')
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => $payment->paid_at?->format('d.m.Y H:i') ?? '—'),
        ];
    }
}
