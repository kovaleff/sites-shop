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
            'moderationStatus' => $this->moderation_status->value,
            'moderationStatusLabel' => $this->moderation_status->label(),
            'moderationNote' => $this->moderation_note,
            'verified' => (bool) $this->verified,
            'verificationFilename' => $this->verification_filename,
            'verificationCheckedAt' => $this->verification_checked_at?->toIso8601String(),
            'verificationError' => $this->verification_error,
            'unpublishReason' => $this->unpublish_reason,
            'paidUntil' => $this->paid_until?->toDateString(),
            'needsRenewal' => $this->needsRenewal(),
            'isPubliclyVisible' => $this->isPubliclyVisible(),
            'soldAt' => $this->sold_at?->toIso8601String(),
            'hiddenAt' => $this->hidden_at?->toIso8601String(),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'unreadMessages' => (int) ($this->unread_messages_count ?? 0),
            'messagesCount' => (int) ($this->messages_count ?? 0),
            'favoritesCount' => (int) ($this->favorites_count ?? 0),
            'viewsToday' => (int) ($this->views_today_sum ?? 0),
            'viewsWeek' => (int) ($this->views_week_sum ?? 0),
            'viewsMonth' => (int) ($this->views_month_sum ?? 0),
            'viewsTotal' => (int) ($this->views_total_sum ?? 0),
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
