<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class MessageSenderFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Отправитель';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['sender'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $value = (string) $this->request->get('sender');

        return $builder->whereHas('sender', function (Builder $query) use ($value): void {
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
            Input::make('sender')
                ->title('Отправитель')
                ->placeholder('Имя или email')
                ->value($this->request->get('sender')),
        ];
    }

    /**
     * Value to be displayed.
     */
    public function value(): string
    {
        return $this->name().': '.$this->request->get('sender');
    }
}
