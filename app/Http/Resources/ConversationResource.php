<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Диалог кабинета (data-model.md §7.4).
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isBuyer = $user !== null && $this->buyer_id === $user->id;
        $counterparty = $isBuyer ? $this->seller : $this->buyer;
        $lastMessage = $this->latestMessage;

        return [
            'id' => $this->id,
            'role' => $isBuyer ? 'buyer' : 'seller',
            'site' => [
                'id' => $this->site->id,
                'title' => $this->site->title,
                'domain' => $this->site->domain,
                'status' => $this->site->status->value,
                'isPubliclyVisible' => $this->site->isPubliclyVisible(),
            ],
            'counterparty' => [
                'id' => $counterparty?->id,
                'displayName' => $counterparty?->name,
                'avatarUrl' => $counterparty?->avatar,
            ],
            'lastMessage' => $lastMessage === null ? null : [
                'body' => $lastMessage->body,
                'createdAt' => $lastMessage->created_at?->toIso8601String(),
                'isMine' => $lastMessage->sender_id === $user?->id,
            ],
            'unreadCount' => (int) ($this->unread_messages_count ?? $this->unreadCountFor($user)),
            'isReadOnly' => $this->isReadOnly(),
            'lastMessageAt' => $this->last_message_at?->toIso8601String(),
        ];
    }
}
