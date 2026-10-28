<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Message;

use App\Models\Message;
use Illuminate\Support\Str;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class MessageListLayout extends Table
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
            TD::make('id', 'ID')
                ->render(fn (Message $message) => Link::make((string) $message->id)
                    ->route('platform.marketplace.messages.show', $message)),

            TD::make('conversation', 'Диалог')
                ->cantHide()
                ->render(fn (Message $message) => Link::make($message->conversation?->site?->domain ?? '—')
                    ->route('platform.marketplace.messages.show', $message)),

            TD::make('sender', 'Отправитель')
                ->render(fn (Message $message) => $message->sender?->name ?? '—'),

            TD::make('body', 'Текст')
                ->render(fn (Message $message) => Str::limit($message->body, 60)),

            TD::make('read_at', 'Прочитано')
                ->render(fn (Message $message) => $message->read_at?->format('d.m.Y H:i') ?? 'Нет'),

            TD::make('created_at', 'Создано')
                ->sort()
                ->alignRight()
                ->render(fn (Message $message) => $message->created_at?->format('d.m.Y H:i')),
        ];
    }
}
