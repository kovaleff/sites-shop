<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Category;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class CategoryEditLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('category.name')
                ->title('Название')
                ->required()
                ->max(255)
                ->help('Уникальное название тематики.'),

            Input::make('category.slug')
                ->title('Публичный идентификатор')
                ->readonly()
                ->help('Генерируется при создании и не изменяется.'),

            Input::make('category.sites_count')
                ->title('Объявлений')
                ->readonly()
                ->help('Тематику с объявлениями удалить нельзя.'),
        ];
    }
}
