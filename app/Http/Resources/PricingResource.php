<?php

namespace App\Http\Resources;

use App\Services\Payments\SubscriptionPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичные цена, сроки и скидки (FR-046).
 */
class PricingResource extends JsonResource
{
    public function __construct(private readonly ?SubscriptionPricing $pricing = null)
    {
        parent::__construct($pricing ?? app(SubscriptionPricing::class));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SubscriptionPricing $pricing */
        $pricing = $this->resource;
        $settings = $pricing->settings();

        return [
            'basePrice' => (float) $settings->base_price,
            'isActive' => (bool) $settings->is_active && $settings->hasConfiguredPrice(),
            'periods' => collect($pricing->periods())
                ->map(fn (array $quote): array => [
                    'months' => $quote['months'],
                    'discountPercent' => $quote['discountPercent'],
                    'total' => $quote['total'],
                    'perMonth' => $quote['perMonth'],
                ])
                ->values()
                ->all(),
        ];
    }
}
