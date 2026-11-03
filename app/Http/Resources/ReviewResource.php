<?php

namespace App\Http\Resources;

use App\Enums\ModerationStatus;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Отзыв покупателя о лоте.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPublished = $this->moderation_status === ModerationStatus::Approved;

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'comment' => $this->comment,
            'status' => $this->moderation_status->value,
            'statusLabel' => $this->moderation_status->label(),
            'isPublished' => $isPublished,
            'createdAt' => $this->created_at?->toIso8601String(),
            'publishedAt' => $this->published_at?->toIso8601String(),
            'author' => $this->whenLoaded('author', fn (): ?array => $this->author === null ? null : [
                'id' => $this->author->id,
                'displayName' => $this->author->name,
                'avatarUrl' => $this->author->avatar,
            ]),
            'site' => $this->whenLoaded('site', fn (): ?array => $this->site === null ? null : [
                'id' => $this->site->id,
                'title' => $this->site->title,
                'domain' => $this->site->domain,
                'url' => $this->site->canonicalPath(),
            ]),
        ];
    }
}
