<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\SubscriptionPayment;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class UserPaymentsTable extends Table
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

            TD::make('site', 'Объявление')
                ->render(fn (SubscriptionPayment $payment) => $payment->site?->domain ?? '—'),

            TD::make('amount', 'Сумма')
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => number_format((float) $payment->amount, 2, '.', ' ')),

            TD::make('status', 'Статус')
                ->render(fn (SubscriptionPayment $payment) => $payment->status->label()),

            TD::make('paid_at', 'Оплачен')
                ->alignRight()
                ->render(fn (SubscriptionPayment $payment) => $payment->paid_at?->format('d.m.Y H:i') ?? '—'),
        ];
    }
}
