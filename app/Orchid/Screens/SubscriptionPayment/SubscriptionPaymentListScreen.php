<?php

declare(strict_types=1);

namespace App\Orchid\Screens\SubscriptionPayment;

use App\Models\SubscriptionPayment;
use App\Orchid\Layouts\SubscriptionPayment\PaymentFiltersLayout;
use App\Orchid\Layouts\SubscriptionPayment\SubscriptionPaymentListLayout;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;

class SubscriptionPaymentListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'payments' => SubscriptionPayment::query()
                ->with(['site', 'payer'])
                ->filters(PaymentFiltersLayout::class)
                ->defaultSort('created_at', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Платежи';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Платежи за подписку на объявления биржи.';
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
            PaymentFiltersLayout::class,
            SubscriptionPaymentListLayout::class,
        ];
    }
}
