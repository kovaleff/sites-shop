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
            'title' => $this->title,
            'domain' => $this->domain,
            'domainZone' => $this->domain_zone,
            'description' => $this->description,
            'price' => (int) $this->price,
            'monthlyIncome' => (int) $this->monthly_revenue,
            'status' => $this->status->value,
            'isVerified' => (bool) $this->verified,
            'faviconUrl' => null,
            'publishedAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
