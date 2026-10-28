<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class SiteCategoryFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Тематика';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['category'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        return $builder->whereHas('categories', function (Builder $query): void {
            $query->where('categories.id', $this->request->get('category'));
        });
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        return [
            Select::make('category')
                ->fromModel(Category::class, 'name', 'id')
                ->empty()
                ->value($this->request->get('category'))
                ->title('Тематика'),
        ];
    }

    /**
     * Value to be displayed.
     */
    public function value(): string
    {
        $category = Category::query()->find($this->request->get('category'));

        return $this->name().': '.($category?->name ?? '—');
    }
}
