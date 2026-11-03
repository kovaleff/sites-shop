<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичный профиль продавца.
 *
 * @mixin User
 */
class PublicSellerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->name,
            'avatarUrl' => $this->avatar,
            'bio' => $this->bio,
            'city' => $this->city,
            'registeredAt' => $this->created_at?->toIso8601String(),
            'isVerifiedSeller' => (int) ($this->verified_sites_count ?? 0) > 0,
            'publishedSitesCount' => (int) ($this->published_sites_count ?? 0),
            'soldSitesCount' => (int) ($this->sold_sites_count ?? 0),
            'ratingAvg' => $this->rating_avg === null ? null : (float) $this->rating_avg,
            'reviewsCount' => (int) ($this->reviews_count ?? 0),
            'profileUrl' => '/seller/'.$this->getKey(),
        ];
    }
}
