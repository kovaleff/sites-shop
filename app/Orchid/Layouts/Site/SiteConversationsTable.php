<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Models\Conversation;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SiteConversationsTable extends Table
{
    /**
     * @var string
     */
    public $target = 'conversations';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->render(fn (Conversation $conversation) => Link::make((string) $conversation->id)
                    ->route('platform.marketplace.conversations.show', $conversation)),

            TD::make('buyer', 'Покупатель')
                ->render(fn (Conversation $conversation) => $conversation->buyer?->name ?? '—'),

            TD::make('seller', 'Продавец')
                ->render(fn (Conversation $conversation) => $conversation->seller?->name ?? '—'),

            TD::make('last_message_at', 'Последнее сообщение')
                ->alignRight()
                ->render(fn (Conversation $conversation) => $conversation->last_message_at?->format('d.m.Y H:i') ?? '—'),
        ];
    }
}
