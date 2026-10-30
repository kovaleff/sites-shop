<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Сообщение диалога (data-model.md §7.5).
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'isMine' => $this->sender_id === $request->user()?->id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'readAt' => $this->read_at?->toIso8601String(),
        ];
    }
}
