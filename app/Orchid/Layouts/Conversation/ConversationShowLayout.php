<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Conversation;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Rows;

class ConversationShowLayout extends Rows
{
    /**
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('conversation.site')
                ->title('Объявление')
                ->readonly(),

            Input::make('conversation.buyer')
                ->title('Покупатель')
                ->readonly(),

            Input::make('conversation.seller')
                ->title('Продавец')
                ->readonly(),

            Input::make('conversation.last_message_at')
                ->title('Последнее сообщение')
                ->readonly(),
        ];
    }
}
