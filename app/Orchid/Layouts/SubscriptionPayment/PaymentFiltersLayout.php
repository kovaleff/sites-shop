<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\SubscriptionPayment;

use App\Orchid\Filters\PaymentDomainsFilter;
use App\Orchid\Filters\PaymentInvoiceFilter;
use App\Orchid\Filters\PaymentMethodFilter;
use App\Orchid\Filters\PaymentPaidAtFilter;
use App\Orchid\Filters\PaymentPayerFilter;
use App\Orchid\Filters\PaymentSiteFilter;
use App\Orchid\Filters\PaymentStatusFilter;
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
            PaymentDomainsFilter::class,
            PaymentStatusFilter::class,
            PaymentInvoiceFilter::class,
            PaymentMethodFilter::class,
            PaymentPaidAtFilter::class,
            PaymentSiteFilter::class,
            PaymentPayerFilter::class,
        ];
    }
}
