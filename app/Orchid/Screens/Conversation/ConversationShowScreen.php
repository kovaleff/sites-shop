<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Conversation;

use App\Models\Conversation;
use App\Orchid\Layouts\Conversation\ConversationMessagesTable;
use App\Orchid\Layouts\Conversation\ConversationShowLayout;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class ConversationShowScreen extends Screen
{
    /**
     * @var Conversation
     */
    public $conversation;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Conversation $conversation): iterable
    {
        $this->conversation = $conversation->load(['site', 'buyer', 'seller']);

        return [
            'conversation' => [
                'site' => $conversation->site?->domain ?? '—',
                'buyer' => $conversation->buyer?->name ?? '—',
                'seller' => $conversation->seller?->name ?? '—',
                'last_message_at' => $conversation->last_message_at?->format('d.m.Y H:i') ?? '—',
            ],
            'messages' => $conversation->messages()
                ->with('sender')
                ->orderBy('created_at')
                ->get(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Диалог';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Переписка участников сделки в хронологическом порядке.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.conversations',
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::block(ConversationShowLayout::class)
                ->title('Участники'),

            Layout::block(ConversationMessagesTable::class)
                ->title('История сообщений'),
        ];
    }
}
