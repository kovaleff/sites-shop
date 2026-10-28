<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class PaymentPayerFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Плательщик';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['payer'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $value = (string) $this->request->get('payer');

        return $builder->whereHas('payer', function (Builder $query) use ($value): void {
            $query->where('name', 'like', '%'.$value.'%')
                ->orWhere('email', 'like', '%'.$value.'%');
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('payer')
                ->title('Плательщик')
                ->placeholder('Имя или email')
                ->value($this->request->get('payer')),
        ];
    }

    /**
     * Value to be displayed.
     */
    public function value(): string
    {
        return $this->name().': '.$this->request->get('payer');
    }
}
