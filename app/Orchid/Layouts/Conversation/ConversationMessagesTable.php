<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Conversation;

use App\Models\Message;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ConversationMessagesTable extends Table
{
    /**
     * @var string
     */
    public $target = 'messages';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('sender', 'Отправитель')
                ->render(fn (Message $message) => $message->sender?->name ?? '—'),

            TD::make('body', 'Текст')
                ->cantHide()
                ->render(fn (Message $message) => $message->body),

            TD::make('created_at', 'Отправлено')
                ->sort()
                ->alignRight()
                ->render(fn (Message $message) => $message->created_at?->format('d.m.Y H:i')),

            TD::make('read_at', 'Прочитано')
                ->alignRight()
                ->render(fn (Message $message) => $message->read_at?->format('d.m.Y H:i') ?? 'Нет'),
        ];
    }
}
