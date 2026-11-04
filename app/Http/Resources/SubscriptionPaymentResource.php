<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Запись об оплате подписки в истории кабинета (data-model.md §10.3).
 *
 * @mixin SubscriptionPayment
 */
class SubscriptionPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $target = $this->target_status !== null
            ? ListingStatus::tryFrom((string) $this->target_status)
            : null;

        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoiceNumber(),
            'siteId' => $this->site_id,
            'siteDomain' => $this->site_domain,
            'currentSiteDomain' => $this->site?->domain,
            'receiptEmail' => $this->receipt_email,
            'basePrice' => $this->base_price === null ? null : (float) $this->base_price,
            'baseAmount' => $this->base_amount === null ? null : (float) $this->base_amount,
            'discountPercent' => (int) $this->discount_percent,
            'discountAmount' => (float) $this->discount_amount,
            'amount' => (int) round((float) $this->amount),
            'currency' => $this->currency,
            'periodMonths' => (int) $this->period_months,
            'targetStatus' => $target?->value,
            'targetStatusLabel' => $target?->label(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'paymentMethod' => $this->payment_method?->value,
            'paymentMethodLabel' => $this->payment_method?->label(),
            'provider' => $this->provider?->value,
            'isTest' => (bool) $this->is_test,
            'qrExpiresAt' => $this->qr_expires_at?->toIso8601String(),
            'paidAt' => $this->paid_at?->toIso8601String(),
            'failureReason' => $this->failure_reason,
            'reviewReason' => $this->review_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
