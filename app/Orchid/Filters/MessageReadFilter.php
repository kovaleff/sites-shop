<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class MessageReadFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Прочтение';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['read'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        return match ($this->request->get('read')) {
            'read' => $builder->whereNotNull('read_at'),
            'unread' => $builder->whereNull('read_at'),
            default => $builder,
        };
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('read')
                ->title('Прочтение')
                ->options([
                    'read' => 'Прочитано',
                    'unread' => 'Не прочитано',
                ])
                ->empty('Любое')
                ->value($this->request->get('read')),
        ];
    }

    /**
     * Value to be displayed.
     */
    public function value(): string
    {
        return $this->name().': '.match ($this->request->get('read')) {
            'read' => 'Прочитано',
            'unread' => 'Не прочитано',
            default => 'Любое',
        };
    }
}
