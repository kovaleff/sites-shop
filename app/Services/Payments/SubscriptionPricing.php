<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentException;
use App\Models\SubscriptionSetting;

/**
 * Серверный расчёт стоимости подписки.
 *
 * Итог всегда выражается целыми рублями: скидка применяется к базовой сумме,
 * затем результат округляется математически, 0,5 — вверх.
 */
class SubscriptionPricing
{
    public function settings(): SubscriptionSetting
    {
        return SubscriptionSetting::current();
    }

    /**
     * @return array{
     *     months: int,
     *     basePrice: float,
     *     baseAmount: float,
     *     discountPercent: int,
     *     discountAmount: float,
     *     total: int,
     *     perMonth: float
     * }
     */
    public function quote(int $months): array
    {
        $settings = $this->settings();
        $basePrice = (float) $settings->base_price;

        if ($months < 1) {
            throw PaymentException::invalidPeriod();
        }

        if (! in_array($months, $settings->normalizedPeriods(), true)) {
            throw PaymentException::invalidPeriod();
        }

        $discountPercent = $settings->discountFor($months);
        $baseAmount = round($basePrice * $months, 2);
        $total = (int) round($baseAmount * (100 - $discountPercent) / 100, 0, PHP_ROUND_HALF_UP);

        if ($total <= 0) {
            throw PaymentException::invalidAmount();
        }

        return [
            'months' => $months,
            'basePrice' => round($basePrice, 2),
            'baseAmount' => round($baseAmount, 2),
            'discountPercent' => $discountPercent,
            'discountAmount' => round($baseAmount - $total, 2),
            'total' => $total,
            'perMonth' => round($total / $months, 2),
        ];
    }

    /**
     * Все доступные сроки с итоговыми суммами.
     *
     * @return array<int, array{
     *     months: int,
     *     basePrice: float,
     *     baseAmount: float,
     *     discountPercent: int,
     *     discountAmount: float,
     *     total: int,
     *     perMonth: float
     * }>
     */
    public function periods(): array
    {
        $settings = $this->settings();

        return collect($settings->normalizedPeriods())
            ->map(fn (int $months): array => $this->quote($months))
            ->values()
            ->all();
    }

    /**
     * Проверяет, что по счёту можно заплатить: тариф включён и цена задана.
     */
    public function assertTariffActive(): void
    {
        $settings = $this->settings();

        if (! $settings->is_active || ! $settings->hasConfiguredPrice()) {
            throw PaymentException::tariffInactive();
        }
    }
}
