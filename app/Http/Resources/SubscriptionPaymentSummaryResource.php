<?php

namespace App\Http\Resources;

use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Сокращённая запись о последней попытке оплаты в списке кабинета.
 *
 * @mixin SubscriptionPayment
 */
class SubscriptionPaymentSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoiceNumber(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'amount' => (int) round((float) $this->amount),
            'periodMonths' => (int) $this->period_months,
            'qrExpiresAt' => $this->qr_expires_at?->toIso8601String(),
            'reviewReason' => $this->review_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
