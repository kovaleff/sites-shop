<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionPaymentRequest;
use App\Http\Resources\PaymentOptionsResource;
use App\Http\Resources\RobokassaStartResource;
use App\Http\Resources\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use App\Services\Payments\PaymentExpiryService;
use App\Services\Payments\PaymentInvoiceService;
use App\Services\Payments\RobokassaGateway;
use App\Services\Payments\SubscriptionPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SitePaymentController extends Controller
{
    public function __construct(
        private readonly SubscriptionPricing $pricing,
        private readonly PaymentInvoiceService $invoices,
        private readonly RobokassaGateway $gateway,
        private readonly PaymentExpiryService $expiry,
    ) {}

    /**
     * Данные для окна оплаты объявления (FR-002, FR-002a).
     */
    public function options(Request $request, int $site): JsonResponse
    {
        $model = $request->user()->sites()->findOrFail($site);

        $this->expiry->expireDueForSite($model);

        return response()->json(
            (new PaymentOptionsResource($model, $request->user(), $this->pricing))->resolve($request),
        );
    }

    /**
     * История попыток оплаты объявления (FR-031, FR-032).
     */
    public function index(Request $request, int $site): AnonymousResourceCollection
    {
        $model = $request->user()->sites()->findOrFail($site);

        $payments = $model->subscriptionPayments()
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get();

        return SubscriptionPaymentResource::collection($payments);
    }

    /**
     * Выставление счёта на продление подписки (FR-003, FR-005–FR-010).
     */
    public function store(StoreSubscriptionPaymentRequest $request, int $site): JsonResponse
    {
        $model = $request->user()->sites()->findOrFail($site);

        $payment = $this->invoices->create(
            $request->user(),
            $model,
            $request->periodMonths(),
            $request->receiptEmail($request->user()),
        );

        return response()->json([
            'data' => (new SubscriptionPaymentResource($payment))->resolve($request),
            'robokassa' => (new RobokassaStartResource(
                $this->gateway->startPayload($payment),
            ))->resolve($request),
        ], 201);
    }

    /**
     * Продолжить оплату по действующей попытке (FR-014).
     */
    public function start(Request $request, SubscriptionPayment $payment): JsonResponse
    {
        $payment = $this->invoices->resume($request->user(), $payment);

        return response()->json([
            'data' => (new SubscriptionPaymentResource($payment->load('site')))->resolve($request),
            'robokassa' => (new RobokassaStartResource(
                $this->gateway->startPayload($payment),
            ))->resolve($request),
        ]);
    }
}
