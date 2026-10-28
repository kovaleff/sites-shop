<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Models\Category;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class SiteEditLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('site.title')
                ->title('Название')
                ->required()
                ->max(255)
                ->help('От 3 до 255 символов.'),

            Input::make('site.domain')
                ->title('Домен')
                ->required()
                ->max(253)
                ->help('Уникален среди активных и неактивных объявлений.'),

            TextArea::make('site.description')
                ->title('Описание')
                ->required()
                ->rows(6)
                ->help('От 50 до 5000 символов.'),

            Input::make('site.price')
                ->title('Цена')
                ->type('number')
                ->step('0.01')
                ->min(0)
                ->required(),

            Input::make('site.monthly_revenue')
                ->title('Доход в месяц')
                ->type('number')
                ->step('0.01')
                ->min(0)
                ->required(),

            Switcher::make('site.has_trademark')
                ->title('Товарный знак')
                ->sendTrueOrFalse(),

            Select::make('site.categories')
                ->title('Тематики')
                ->fromModel(Category::class, 'name', 'id')
                ->multiple()
                ->required(),

            Input::make('owner_name')
                ->title('Владелец')
                ->readonly(),
        ];
    }
}
