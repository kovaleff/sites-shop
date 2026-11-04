<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

/**
 * Фильтр по способу оплаты; пустое значение — старые записи без способа.
 */
class PaymentMethodFilter extends Filter
{
    public function name(): string
    {
        return 'Способ оплаты';
    }

    /**
     * @return array<int, string>
     */
    public function parameters(): array
    {
        return ['payment_method_filter'];
    }

    public function run(Builder $builder): Builder
    {
        $value = (string) $this->request->get('payment_method_filter');

        if ($value === '') {
            return $builder;
        }

        if ($value === 'none') {
            return $builder->whereNull('payment_method');
        }

        return $builder->where('payment_method', $value);
    }

    public function display(): array
    {
        return [
            Select::make('payment_method_filter')
                ->title('Способ оплаты')
                ->empty('Все', '')
                ->options([
                    PaymentMethod::Sbp->value => PaymentMethod::Sbp->label(),
                    'none' => 'Не указан',
                ])
                ->value((string) $this->request->get('payment_method_filter')),
        ];
    }

    public function value(): string
    {
        return $this->name().': '.$this->request->get('payment_method_filter');
    }
}
