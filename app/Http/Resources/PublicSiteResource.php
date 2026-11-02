<?php

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Лот публичного каталога (контракт 003, data-model.md §7.3).
 *
 * @mixin Site
 */
class PublicSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->id.'-'.$this->slug(),
            'url' => $this->canonicalPath(),
            'title' => $this->title,
            'domain' => $this->domain,
            'domainZone' => $this->domain_zone,
            'description' => $this->description,
            'price' => (int) $this->price,
            'monthlyIncome' => (int) $this->monthly_revenue,
            'paybackMonths' => $this->paybackMonths(),
            'hasTrademark' => (bool) $this->has_trademark,
            'status' => $this->status->value,
            'isVerified' => (bool) $this->verified,
            'faviconUrl' => null,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'seller' => $this->whenLoaded('owner', fn (): ?array => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'displayName' => $this->owner->name,
                'avatarUrl' => $this->owner->avatar,
                'profileUrl' => '/seller/'.$this->owner->id,
            ]),
            'publishedAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
