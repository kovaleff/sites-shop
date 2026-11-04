<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

/**
 * Поиск платежа по номеру счёта (он же `InvId`).
 */
class PaymentInvoiceFilter extends Filter
{
    public function name(): string
    {
        return 'Номер счёта';
    }

    /**
     * @return array<int, string>
     */
    public function parameters(): array
    {
        return ['invoice'];
    }

    public function run(Builder $builder): Builder
    {
        $invoice = (int) $this->request->get('invoice');

        if ($invoice <= 0) {
            return $builder;
        }

        return $builder->whereKey($invoice);
    }

    public function display(): array
    {
        return [
            Input::make('invoice')
                ->title('Номер счёта')
                ->type('number')
                ->min(1)
                ->placeholder('Например, 42')
                ->value($this->request->get('invoice')),
        ];
    }

    public function value(): string
    {
        return $this->name().': '.$this->request->get('invoice');
    }
}
