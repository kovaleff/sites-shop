<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

/**
 * Фильтр по одному или нескольким статусам платежа.
 */
class PaymentStatusFilter extends Filter
{
    public function name(): string
    {
        return 'Статус';
    }

    /**
     * @return array<int, string>
     */
    public function parameters(): array
    {
        return ['payment_status'];
    }

    public function run(Builder $builder): Builder
    {
        $statuses = array_values(array_filter((array) $this->request->get('payment_status', [])));

        if ($statuses === []) {
            return $builder;
        }

        return $builder->whereIn('status', $statuses);
    }

    public function display(): array
    {
        return [
            Select::make('payment_status')
                ->title('Статус')
                ->multiple()
                ->options(
                    collect(PaymentStatus::cases())
                        ->mapWithKeys(fn (PaymentStatus $status): array => [$status->value => $status->label()])
                        ->all(),
                )
                ->value((array) $this->request->get('payment_status', [])),
        ];
    }

    public function value(): string
    {
        $statuses = (array) $this->request->get('payment_status', []);

        return $this->name().': '.implode(', ', $statuses);
    }
}
