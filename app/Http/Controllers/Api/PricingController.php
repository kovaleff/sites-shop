<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PricingResource;
use App\Services\Payments\SubscriptionPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /**
     * Публичные цена, сроки и скидки для страницы «Тарифы» (FR-046).
     */
    public function __invoke(Request $request, SubscriptionPricing $pricing): JsonResponse
    {
        return response()->json(
            (new PricingResource($pricing))->resolve($request),
        );
    }
}
