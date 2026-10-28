<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Message;

use App\Models\Message;
use App\Orchid\Layouts\Message\MessageFiltersLayout;
use App\Orchid\Layouts\Message\MessageListLayout;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;

class MessageListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'messages' => Message::query()
                ->with(['conversation.site', 'sender'])
                ->filters(MessageFiltersLayout::class)
                ->defaultSort('created_at', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Сообщения';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Все сообщения диалогов биржи.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.messages',
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
            MessageFiltersLayout::class,
            MessageListLayout::class,
        ];
    }
}
