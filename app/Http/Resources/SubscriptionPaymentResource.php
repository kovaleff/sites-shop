<?php

namespace App\Http\Resources;

use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Запись об оплате подписки в истории кабинета (data-model.md §7.2).
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
        return [
            'id' => $this->id,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'periodMonths' => $this->period_months,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'paidAt' => $this->paid_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
