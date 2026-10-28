<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class MessageConversationFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Диалог';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['conversation'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $value = (string) $this->request->get('conversation');

        return $builder->where(function (Builder $query) use ($value): void {
            if (ctype_digit($value)) {
                $query->where('conversation_id', (int) $value);
            }

            $query->orWhereHas('conversation.site', function (Builder $site) use ($value): void {
                $site->where('domain', 'like', '%'.$value.'%')
                    ->orWhere('title', 'like', '%'.$value.'%');
            });
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('conversation')
                ->title('Диалог')
                ->placeholder('ID диалога, домен или название объявления')
                ->value($this->request->get('conversation')),
        ];
    }

    /**
     * Value to be displayed.
     */
    public function value(): string
    {
        return $this->name().': '.$this->request->get('conversation');
    }
}
