<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class ConversationSiteFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Объявление';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['site'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $value = (string) $this->request->get('site');

        return $builder->whereHas('site', function (Builder $query) use ($value): void {
            $query->where('domain', 'like', '%'.$value.'%')
                ->orWhere('title', 'like', '%'.$value.'%');
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('site')
                ->title('Объявление')
                ->placeholder('Домен или название')
                ->value($this->request->get('site')),
        ];
    }

    /**
     * Value to be displayed.
     */
    public function value(): string
    {
        return $this->name().': '.$this->request->get('site');
    }
}
