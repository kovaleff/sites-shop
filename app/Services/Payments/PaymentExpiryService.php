<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Site;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\Log;

/**
 * Истечение срока действия QR-кода.
 *
 * Помимо планировщика (`payments:expire-pending`) срок проверяется лениво
 * при открытии окна оплаты и запросе статуса, чтобы продавец не увидел
 * просроченный QR между запусками команды.
 */
class PaymentExpiryService
{
    /**
     * Отменяет все просроченные `pending` попытки.
     *
     * @return int Количество отменённых попыток
     */
    public function expirePending(): int
    {
        $expired = SubscriptionPayment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('qr_expires_at')
            ->where('qr_expires_at', '<', now())
            ->get();

        foreach ($expired as $payment) {
            $this->cancel($payment);
        }

        return $expired->count();
    }

    /**
     * Ленивая проверка одной попытки.
     */
    public function expireIfDue(SubscriptionPayment $payment): bool
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return false;
        }

        if ($payment->qr_expires_at === null || ! $payment->qr_expires_at->isPast()) {
            return false;
        }

        $this->cancel($payment);

        return true;
    }

    /**
     * Ленивая проверка всех открытых попыток объявления.
     */
    public function expireDueForSite(Site $site): void
    {
        SubscriptionPayment::query()
            ->where('site_id', $site->getKey())
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('qr_expires_at')
            ->where('qr_expires_at', '<', now())
            ->get()
            ->each(fn (SubscriptionPayment $payment) => $this->cancel($payment));
    }

    private function cancel(SubscriptionPayment $payment): void
    {
        $fresh = SubscriptionPayment::query()->find($payment->getKey());

        if ($fresh === null || $fresh->status !== PaymentStatus::Pending) {
            return;
        }

        $fresh->forceFill([
            'status' => PaymentStatus::Cancelled,
            'failure_reason' => 'expired',
        ])->save();

        Log::info('subscription_payment.expired', [
            'inv_id' => $fresh->getKey(),
            'site_id' => $fresh->site_id,
        ]);
    }
}
