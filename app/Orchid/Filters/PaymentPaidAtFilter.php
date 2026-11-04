<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

/**
 * Диапазон дат оплаты.
 */
class PaymentPaidAtFilter extends Filter
{
    public function name(): string
    {
        return 'Дата оплаты';
    }

    /**
     * @return array<int, string>
     */
    public function parameters(): array
    {
        return ['paid_from', 'paid_to'];
    }

    public function run(Builder $builder): Builder
    {
        $from = $this->request->get('paid_from');
        $to = $this->request->get('paid_to');

        return $builder
            ->when($from, fn (Builder $query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('paid_at', '<=', $to));
    }

    public function display(): array
    {
        return [
            Input::make('paid_from')
                ->title('Оплачен с')
                ->type('date')
                ->value($this->request->get('paid_from')),

            Input::make('paid_to')
                ->title('Оплачен по')
                ->type('date')
                ->value($this->request->get('paid_to')),
        ];
    }

    public function value(): string
    {
        return $this->name().': '.$this->request->get('paid_from').' — '.$this->request->get('paid_to');
    }
}
