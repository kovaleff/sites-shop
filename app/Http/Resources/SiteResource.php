<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Объявление в кабинете продавца (data-model.md §7.1).
 *
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isDetail = $this->resource->relationLoaded('subscriptionPayments');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'domain' => $this->domain,
            'domainZone' => $this->domain_zone,
            'description' => $this->description,
            'price' => (int) $this->price,
            'monthlyRevenue' => (int) $this->monthly_revenue,
            'hasTrademark' => (bool) $this->has_trademark,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'verified' => (bool) $this->verified,
            'verificationFilename' => $this->verification_filename,
            'paidUntil' => $this->paid_until?->toDateString(),
            'needsRenewal' => $this->needsRenewal(),
            'isPubliclyVisible' => $this->isPubliclyVisible(),
            'soldAt' => $this->sold_at?->toIso8601String(),
            'hiddenAt' => $this->hidden_at?->toIso8601String(),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'unreadMessages' => (int) ($this->unread_messages_count ?? 0),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            $this->mergeWhen($isDetail, fn (): array => [
                'payments' => SubscriptionPaymentResource::collection(
                    $this->subscriptionPayments->sortByDesc('created_at')->values()
                ),
                'availableStatuses' => $this->availableStatuses(),
            ]),
        ];
    }

    /**
     * Допустимые переходы статуса для меню кабинета.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function availableStatuses(): array
    {
        return collect($this->status->allowedTransitions())
            ->map(fn (ListingStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();
    }
}
