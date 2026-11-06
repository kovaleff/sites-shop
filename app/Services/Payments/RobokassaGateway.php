<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Str;

/**
 * Подготовка платёжных данных Robokassa и проверка серверных уведомлений.
 *
 * Секреты (`Пароль#1`, `Пароль#2`) остаются на сервере: в браузер уходит
 * только одноразовая подпись конкретного счёта.
 */
class RobokassaGateway
{
    /**
     * Проверяет, включён ли тестовый режим для текущего окружения.
     */
    public function isTestMode(): bool
    {
        return (bool) config('services.robokassa.test_mode')
            && ! app()->isProduction();
    }

    /**
     * @return array{login: string, password1: string, password2: string, isTest: bool}
     */
    public function credentials(): array
    {
        return $this->credentialsFor($this->isTestMode());
    }

    /**
     * Реквизиты для конкретного режима — тестовые или боевые.
     *
     * @return array{login: string, password1: string, password2: string, isTest: bool}
     */
    public function credentialsFor(bool $isTest): array
    {
        $isTest = $isTest && ! app()->isProduction();
        $config = config('services.robokassa');

        return [
            'login' => (string) ($isTest
                ? $config['test_merchant_login'] ?? ''
                : $config['merchant_login'] ?? ''),
            'password1' => (string) ($isTest
                ? $config['test_password1'] ?? ''
                : $config['password1'] ?? ''),
            'password2' => (string) ($isTest
                ? $config['test_password2'] ?? ''
                : $config['password2'] ?? ''),
            'isTest' => $isTest,
        ];
    }

    /**
     * Пароль #2 для проверки callback по платежу.
     */
    public function password2For(bool $isTest): string
    {
        return $this->credentialsFor($isTest)['password2'];
    }

    public function scriptUrl(): string
    {
        return (string) config(
            'services.robokassa.script_url',
            'https://auth.robokassa.ru/merchant/bundle/robokassa-iframe-badge.js',
        );
    }

    /**
     * Фискальный чек для счёта. Наименование включает домен-снимок (FR-007a).
     *
     * @return array{sno: string, items: array<int, array<string, mixed>>}
     */
    public function  receipt(SubscriptionPayment $payment, string $domain): array
    {
        $months = (int) $payment->period_months;

        return [
            'sno' => (string) config('services.robokassa.sno', 'osn'),
            'items' => [
                [
                    'name' => Str::limit(
                        "Продление подписки на домен {$domain} ({$months} мес.)",
                        128,
                        '',
                    ),
                    'quantity' => 1,
                    'sum' => (int) round((float) $payment->amount),
                    'payment_method' => 'full_payment',
                    'payment_object' => (string) config('services.robokassa.payment_object', 'service'),
                    'tax' => (string) config('services.robokassa.tax', 'none'),
                ],
            ],
        ];
    }

    /**
     * JSON-строка чека; сохраняется в платеже, чтобы подпись не менялась.
     */
    public function receiptJson(SubscriptionPayment $payment, string $domain): string
    {
        $encoded = json_encode(
            $this->receipt($payment, $domain),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Сумма счёта строкой в целых рублях — та же строка участвует в подписи.
     */
    public function outSum(SubscriptionPayment $payment): string
    {
        return (string) (int) round((float) $payment->amount);
    }

    /**
     * Подпись запроса на выставление счёта:
     * md5(login:outSum:invId:urlencode(receipt):Пароль#1).
     */
    public function signature(
        string $login,
        string $outSum,
        int|string $invId,
        string $receipt,
        string $password1,
    ): string {
        return md5(implode(':', [$login, $outSum, (string) $invId, urlencode($receipt), $password1]));
    }

    /**
     * Параметры для `Robokassa.pay.startOp({ paymentMethod: 'SBP', … })`.
     *
     * @return array{
     *     merchantLogin: string,
     *     outSum: string,
     *     invId: int,
     *     receipt: string,
     *     signature: string,
     *     isTest: bool,
     *     scriptUrl: string
     * }
     */
    public function startPayload(SubscriptionPayment $payment): array
    {
        $credentials = $this->credentialsFor((bool) $payment->is_test);
        $outSum = $this->outSum($payment);
        $invId = $payment->invoiceNumber();
        $receipt = $this->receiptJson($payment, (string) $payment->snapshotDomain());

        return [
            'merchantLogin' => $credentials['login'],
            'outSum' => $outSum,
            'invId' => $invId,
            'receipt' => $receipt,
            'signature' => $this->signature(
                $credentials['login'],
                $outSum,
                $invId,
                $receipt,
                $credentials['password1'],
            ),
            'isTest' => $credentials['isTest'],
            'scriptUrl' => $this->scriptUrl(),
        ];
    }

    /**
     * Проверка подписи серверного уведомления:
     * md5(OutSum:InvId:Пароль#2).
     */
    public function verifySignature(
        string $outSum,
        int|string $invId,
        string $signature,
        string $password2,
    ): bool {
        $expected = md5(implode(':', [$outSum, (string) $invId, $password2]));

        return hash_equals($expected, Str::lower(trim($signature)));
    }

    /**
     * Способ оплаты в уведомлении совпадает с ожидаемым СБП.
     */
    public function isSbpMethod(?string $method): bool
    {
        if ($method === null || $method === '') {
            return true;
        }

        return Str::upper(trim($method)) === PaymentMethod::Sbp->providerCode();
    }

    /**
     * Сравнение суммы уведомления со счётом (до копейки).
     */
    public function amountsMatch(string $callbackOutSum, float $expectedAmount): bool
    {
        return abs((float) $callbackOutSum - round($expectedAmount, 2)) < 0.005;
    }
}
