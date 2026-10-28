<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\SubscriptionPayment;

use App\Orchid\Filters\PaymentPayerFilter;
use App\Orchid\Filters\PaymentSiteFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class PaymentFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            PaymentSiteFilter::class,
            PaymentPayerFilter::class,
        ];
    }
}
