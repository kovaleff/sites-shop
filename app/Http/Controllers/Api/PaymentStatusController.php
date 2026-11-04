<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use App\Services\Payments\PaymentExpiryService;
use Illuminate\Http\Request;

class PaymentStatusController extends Controller
{
    public function __construct(private readonly PaymentExpiryService $expiry) {}

    /**
     * Текущее состояние попытки оплаты для polling-а кабинета (FR-013, FR-033).
     */
    public function show(Request $request, SubscriptionPayment $payment): SubscriptionPaymentResource
    {
        if ($payment->user_id !== $request->user()->getKey()) {
            abort(404);
        }

        $this->expiry->expireIfDue($payment);

        return new SubscriptionPaymentResource($payment->refresh()->load('site'));
    }
}
