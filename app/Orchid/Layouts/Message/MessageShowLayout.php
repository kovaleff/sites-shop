<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Message;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Layouts\Rows;

class MessageShowLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('message.conversation_id')
                ->title('Диалог')
                ->readonly(),

            Input::make('message.site')
                ->title('Объявление')
                ->readonly(),

            Input::make('message.sender')
                ->title('Отправитель')
                ->readonly(),

            TextArea::make('message.body')
                ->title('Текст')
                ->rows(10)
                ->readonly(),

            Input::make('message.created_at')
                ->title('Отправлено')
                ->readonly(),

            Input::make('message.read_at')
                ->title('Прочитано')
                ->readonly(),
        ];
    }
}
