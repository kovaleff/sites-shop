<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class ConversationParticipantFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Участник';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['participant'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $value = (string) $this->request->get('participant');

        return $builder->where(function (Builder $query) use ($value): void {
            $query->whereHas('buyer', fn (Builder $buyer) => $this->matchUser($buyer, $value))
                ->orWhereHas('seller', fn (Builder $seller) => $this->matchUser($seller, $value));
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Input::make('participant')
                ->title('Участник')
                ->placeholder('Имя или email покупателя/продавца')
                ->value($this->request->get('participant')),
        ];
    }

    /**
     * Value to be displayed.
     */
    public function value(): string
    {
        return $this->name().': '.$this->request->get('participant');
    }

    /**
     * Match a participant by name or email.
     */
    private function matchUser(Builder $query, string $value): void
    {
        $query->where('name', 'like', '%'.$value.'%')
            ->orWhere('email', 'like', '%'.$value.'%');
    }
}
