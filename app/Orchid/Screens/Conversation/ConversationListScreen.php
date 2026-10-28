<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Conversation;

use App\Models\Conversation;
use App\Orchid\Layouts\Conversation\ConversationFiltersLayout;
use App\Orchid\Layouts\Conversation\ConversationListLayout;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;

class ConversationListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'conversations' => Conversation::query()
                ->with(['site', 'buyer', 'seller'])
                ->withCount('messages')
                ->filters(ConversationFiltersLayout::class)
                ->defaultSort('last_message_at', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Диалоги';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Переписка покупателей и продавцов по объявлениям.';
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
     * @return string[]|Layout[]
     */
    public function layout(): iterable
    {
        return [
            ConversationFiltersLayout::class,
            ConversationListLayout::class,
        ];
    }
}
