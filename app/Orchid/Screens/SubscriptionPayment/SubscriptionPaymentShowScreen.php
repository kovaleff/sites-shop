<?php

declare(strict_types=1);

namespace App\Orchid\Screens\SubscriptionPayment;

use App\Models\SubscriptionPayment;
use App\Orchid\Layouts\SubscriptionPayment\SubscriptionPaymentShowLayout;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;

class SubscriptionPaymentShowScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(SubscriptionPayment $subscriptionPayment): iterable
    {
        $subscriptionPayment->load(['site', 'payer']);

        return [
            'payment' => [
                'site' => $subscriptionPayment->site?->domain ?? '—',
                'payer' => $subscriptionPayment->payer?->name ?? '—',
                'amount' => number_format((float) $subscriptionPayment->amount, 2, '.', ' '),
                'currency' => $subscriptionPayment->currency,
                'period_months' => $subscriptionPayment->period_months,
                'status' => $subscriptionPayment->status->label(),
                'paid_at' => $subscriptionPayment->paid_at?->format('d.m.Y H:i') ?? '—',
                'external_id' => $subscriptionPayment->external_id,
                'paid_until' => $subscriptionPayment->site?->paid_until?->format('d.m.Y') ?? '—',
            ],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Платёж';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Данные платежа за подписку на объявление.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.payments',
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|Layout[]
     */
    public function layout(): iterable
    {
        return [
            SubscriptionPaymentShowLayout::class,
        ];
    }
}
