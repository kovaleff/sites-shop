<?php

namespace App\Services\Payments;

use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\PaymentRequiresReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Применение серверного подтверждения Robokassa.
 *
 * Подписка продлевается ровно один раз: повторные уведомления идемпотентны,
 * а спорные случаи переводятся в «Требует проверки» без продления.
 */
class PaymentFinalizer
{
    public function __construct(private readonly RobokassaGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: string}
     */
    public function handleCallback(array $payload): array
    {
        $invId = (int) ($payload['InvId'] ?? 0);
        $outSum = (string) ($payload['OutSum'] ?? '');
        $signature = (string) ($payload['SignatureValue'] ?? '');
        $okBody = 'OK'.$invId;

        /** @var SubscriptionPayment|null $payment */
        $payment = SubscriptionPayment::query()->find($invId);

        if ($payment === null) {
            Log::warning('subscription_payment.callback_unknown_invoice', ['inv_id' => $invId]);

            return ['status' => 200, 'body' => $okBody];
        }

        if ($payment->is_test && app()->isProduction()) {
            Log::warning('subscription_payment.callback_test_rejected', ['inv_id' => $invId]);

            return ['status' => 400, 'body' => 'ERROR'];
        }

        $password2 = $this->gateway->password2For((bool) $payment->is_test);

        if (! $this->gateway->verifySignature($outSum, $invId, $signature, $password2)) {
            Log::warning('subscription_payment.callback_bad_signature', [
                'inv_id' => $invId,
                'site_id' => $payment->site_id,
            ]);

            return ['status' => 400, 'body' => 'ERROR'];
        }

        $needsReview = DB::transaction(function () use ($payment, $payload, $outSum): bool {
            /** @var SubscriptionPayment $locked */
            $locked = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status === PaymentStatus::Paid) {
                return false;
            }

            /** @var Site $site */
            $site = Site::query()->lockForUpdate()->findOrFail($locked->site_id);

            $providerPayload = $this->sanitizedPayload($payload);
            $reviewReason = $this->reviewReason($locked, $site, $payload, $outSum);

            if ($reviewReason !== null) {
                $locked->forceFill([
                    'status' => PaymentStatus::RequiresReview,
                    'paid_at' => now(),
                    'provider_payload' => $providerPayload,
                    'review_reason' => $reviewReason,
                ])->save();

                Log::warning('subscription_payment.requires_review', [
                    'inv_id' => $locked->getKey(),
                    'site_id' => $locked->site_id,
                    'reason' => $reviewReason,
                ]);

                return true;
            }

            $this->applyPaid($locked, $site, $providerPayload);

            return false;
        });

        if ($needsReview) {
            $this->notifyAdmins($payment->refresh());
        }

        return ['status' => 200, 'body' => $okBody];
    }

    /**
     * Применить оплату: продлить подписку и выставить целевой статус.
     * Вызывается внутри транзакции с заблокированными платежом и объявлением.
     *
     * @param  array<string, mixed>  $providerPayload
     */
    public function applyPaid(
        SubscriptionPayment $payment,
        Site $site,
        array $providerPayload = [],
    ): void {
        $target = $payment->target_status !== null
            ? (ListingStatus::tryFrom((string) $payment->target_status) ?? ListingStatus::Active)
            : ListingStatus::Active;

        $site->applyPaidSubscription((int) $payment->period_months, $target);

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'failure_reason' => null,
            'review_reason' => null,
            ...($providerPayload === [] ? [] : ['provider_payload' => $providerPayload]),
        ])->save();

        Log::info('subscription_payment.paid', [
            'inv_id' => $payment->getKey(),
            'site_id' => $site->getKey(),
            'user_id' => $payment->user_id,
            'amount' => $payment->amount,
            'period_months' => $payment->period_months,
            'is_test' => $payment->is_test,
        ]);
    }

    /**
     * Причина, по которой платёж нельзя применить автоматически.
     *
     * @param  array<string, mixed>  $payload
     */
    private function reviewReason(
        SubscriptionPayment $payment,
        Site $site,
        array $payload,
        string $outSum,
    ): ?string {
        if (! $this->gateway->isSbpMethod($payload['PaymentMethod'] ?? null)) {
            return 'method_mismatch';
        }

        $currency = $payload['IncCurrLabel'] ?? null;

        if (is_string($currency) && $currency !== '' && mb_strtoupper($currency) !== 'RUB') {
            return 'currency_mismatch';
        }

        if (! $this->gateway->amountsMatch($outSum, (float) $payment->amount)) {
            return 'amount_mismatch';
        }

        if ($site->status === ListingStatus::Sold) {
            return 'site_sold';
        }

        if (! $site->acceptsPayments()) {
            return 'site_not_payable';
        }

        $target = $payment->target_status !== null
            ? (ListingStatus::tryFrom((string) $payment->target_status) ?? ListingStatus::Active)
            : ListingStatus::Active;

        if ($site->hasDomainConflictFor($target)) {
            return 'domain_conflict';
        }

        return null;
    }

    /**
     * Поля callback без подписи — для аудита и разбора.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizedPayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip([
            'OutSum',
            'InvId',
            'PaymentMethod',
            'IncCurrLabel',
            'Fee',
            'EMail',
        ]));
    }

    private function notifyAdmins(SubscriptionPayment $payment): void
    {
        $admins = User::query()->byAccess('platform.marketplace.payments')->get();

        if ($admins->isEmpty()) {
            Log::warning('subscription_payment.no_review_recipients', [
                'inv_id' => $payment->getKey(),
            ]);

            return;
        }

        $admins->each(fn (User $admin) => $admin->notify(new PaymentRequiresReview($payment)));
    }
}
