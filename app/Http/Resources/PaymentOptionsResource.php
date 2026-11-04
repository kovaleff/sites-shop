<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Payments\SubscriptionPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Данные для окна оплаты объявления (FR-002, FR-002a, FR-014).
 */
class PaymentOptionsResource extends JsonResource
{
    public function __construct(
        private readonly Site $site,
        private readonly User $user,
        private readonly SubscriptionPricing $pricing,
    ) {
        parent::__construct($site);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->pricing->settings();
        $target = $settings->targetListingStatus();
        $activePayment = $this->activePayment();
        $blocked = $this->blockedReason($activePayment, $target);

        return [
            'canPay' => $blocked === null,
            'blockedReason' => $blocked?->errorCode,
            'blockedMessage' => $blocked?->getMessage(),
            'basePrice' => (float) $settings->base_price,
            'periods' => collect($this->pricing->periods())
                ->map(fn (array $quote): array => [
                    'months' => $quote['months'],
                    'discountPercent' => $quote['discountPercent'],
                    'total' => $quote['total'],
                    'perMonth' => $quote['perMonth'],
                ])
                ->values()
                ->all(),
            'defaultReceiptEmail' => $this->user->hasDeliverableEmail()
                ? (string) $this->user->email
                : null,
            'hasDeliverableEmail' => $this->user->hasDeliverableEmail(),
            'targetStatus' => $target->value,
            'targetStatusLabel' => $target->label(),
            'willBeHidden' => $target === ListingStatus::Inactive,
            'activePayment' => $activePayment === null
                ? null
                : (new SubscriptionPaymentSummaryResource($activePayment))->resolve($request),
            'site' => [
                'id' => $this->site->id,
                'domain' => $this->site->domain,
                'status' => $this->site->status->value,
                'statusLabel' => $this->site->status->label(),
            ],
        ];
    }

    private function activePayment(): ?SubscriptionPayment
    {
        return SubscriptionPayment::query()
            ->where('site_id', $this->site->getKey())
            ->whereIn('status', ['pending', 'requires_review'])
            ->latest('id')
            ->first();
    }

    private function blockedReason(
        ?SubscriptionPayment $activePayment,
        ListingStatus $target,
    ): ?PaymentException {
        if ($this->site->status === ListingStatus::Sold) {
            return PaymentException::sold();
        }

        if ($activePayment !== null && $activePayment->status === PaymentStatus::RequiresReview) {
            return PaymentException::underReview();
        }

        if (! $this->pricing->settings()->is_active || ! $this->pricing->settings()->hasConfiguredPrice()) {
            return PaymentException::tariffInactive();
        }

        if ($this->site->hasDomainConflictFor($target)) {
            return PaymentException::domainConflict();
        }

        return null;
    }
}
