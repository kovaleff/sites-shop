<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Category;

use App\Models\Category;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class CategoryListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'categories';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('name', 'Название')
                ->sort()
                ->cantHide()
                ->render(fn (Category $category) => Link::make($category->name)
                    ->route('platform.marketplace.categories.edit', $category)),

            TD::make('slug', 'Публичный идентификатор')
                ->render(fn (Category $category) => $category->slug),

            TD::make('sites_count', 'Объявлений')
                ->alignRight()
                ->render(fn (Category $category) => $category->sites_count),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (Category $category) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Edit'))
                            ->route('platform.marketplace.categories.edit', $category)
                            ->icon('bs.pencil'),

                        Button::make(__('Delete'))
                            ->icon('bs.trash3')
                            ->confirm('Тематика будет удалена безвозвратно. Тематики с объявлениями удалить нельзя.')
                            ->method('remove')
                            ->parameters(['id' => $category->id]),
                    ])),
        ];
    }
}
