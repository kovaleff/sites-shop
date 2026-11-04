<?php

namespace App\Services\Payments;

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Выставление счёта на продление подписки объявления.
 *
 * Все условия фиксируются снимком на момент создания: изменение тарифа
 * влияет только на новые счета.
 */
class PaymentInvoiceService
{
    public function __construct(
        private readonly SubscriptionPricing $pricing,
        private readonly RobokassaGateway $gateway,
    ) {}

    public function create(User $user, Site $site, int $months, ?string $receiptEmail): SubscriptionPayment
    {
        $this->pricing->assertTariffActive();

        if ($receiptEmail === null || $receiptEmail === '') {
            throw PaymentException::receiptEmailRequired();
        }

        return DB::transaction(function () use ($user, $site, $months, $receiptEmail): SubscriptionPayment {
            /** @var Site $lockedSite */
            $lockedSite = Site::query()->lockForUpdate()->findOrFail($site->getKey());

            if (! $lockedSite->isOwnedBy($user)) {
                abort(404);
            }

            if ($lockedSite->status === ListingStatus::Sold) {
                throw PaymentException::sold();
            }

            if (! $lockedSite->acceptsPayments()) {
                throw PaymentException::sold();
            }

            $quote = $this->pricing->quote($months);
            $settings = $this->pricing->settings();
            $targetStatus = $settings->targetListingStatus();

            $this->assertNoOpenReview($lockedSite);
            $this->cancelPendingAttempts($lockedSite);

            if ($lockedSite->hasDomainConflictFor($targetStatus)) {
                throw PaymentException::domainConflict();
            }

            $payment = SubscriptionPayment::query()->create([
                'site_id' => $lockedSite->getKey(),
                'user_id' => $user->getKey(),
                'site_domain' => $lockedSite->domain,
                'amount' => $quote['total'],
                'base_price' => $quote['basePrice'],
                'base_amount' => $quote['baseAmount'],
                'discount_percent' => $quote['discountPercent'],
                'discount_amount' => $quote['discountAmount'],
                'currency' => 'RUB',
                'period_months' => $months,
                'target_status' => $targetStatus->value,
                'receipt_email' => $receiptEmail,
                'status' => PaymentStatus::Pending,
                'qr_expires_at' => now()->addMinutes($settings->qr_ttl_minutes),
                'provider' => PaymentProvider::Robokassa->value,
                'payment_method' => PaymentMethod::Sbp->value,
                'is_test' => $this->gateway->isTestMode(),
            ]);

            $payment->forceFill([
                'receipt_payload' => $this->gateway->receipt($payment, (string) $lockedSite->domain),
            ])->save();

            Log::info('subscription_payment.created', [
                'inv_id' => $payment->getKey(),
                'site_id' => $lockedSite->getKey(),
                'user_id' => $user->getKey(),
                'amount' => $payment->amount,
                'period_months' => $months,
                'is_test' => $payment->is_test,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Продолжение оплаты по действующей попытке.
     */
    public function resume(User $user, SubscriptionPayment $payment): SubscriptionPayment
    {
        if ($payment->user_id !== $user->getKey()) {
            abort(404);
        }

        app(PaymentExpiryService::class)->expireIfDue($payment);

        $payment->refresh();

        if ($payment->status !== PaymentStatus::Pending) {
            throw PaymentException::notPayable();
        }

        return $payment;
    }

    private function assertNoOpenReview(Site $site): void
    {
        $hasReview = SubscriptionPayment::query()
            ->where('site_id', $site->getKey())
            ->where('status', PaymentStatus::RequiresReview)
            ->exists();

        if ($hasReview) {
            throw PaymentException::underReview();
        }
    }

    private function cancelPendingAttempts(Site $site): void
    {
        SubscriptionPayment::query()
            ->where('site_id', $site->getKey())
            ->where('status', PaymentStatus::Pending)
            ->get()
            ->each(function (SubscriptionPayment $pending): void {
                $pending->forceFill([
                    'status' => PaymentStatus::Cancelled,
                    'failure_reason' => 'new_attempt',
                ])->save();
            });
    }
}
