<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Message;

use App\Models\Message;
use App\Orchid\Layouts\Message\MessageShowLayout;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;

class MessageShowScreen extends Screen
{
    /**
     * @var Message
     */
    public $message;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Message $message): iterable
    {
        $this->message = $message->load(['conversation.site', 'sender']);

        return [
            'message' => [
                'conversation_id' => $message->conversation_id,
                'site' => $message->conversation?->site?->domain ?? '—',
                'sender' => $message->sender?->name ?? '—',
                'body' => $message->body,
                'created_at' => $message->created_at?->format('d.m.Y H:i'),
                'read_at' => $message->read_at?->format('d.m.Y H:i') ?? 'Нет',
            ],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Сообщение';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Полный текст сообщения и служебные отметки.';
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
            MessageShowLayout::class,
        ];
    }
}
