<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Бизнес-ошибка оплаты: машиночитаемый код + понятный продавцу текст.
 *
 * Коды соответствуют `contracts/ui-contract.md` §9 и `data-model.md` §6.3.
 */
class PaymentException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->status);
    }

    public static function sold(): self
    {
        return new self('sold', 'Проданное объявление нельзя оплатить.');
    }

    public static function underReview(): self
    {
        return new self(
            'under_review',
            'Предыдущий платёж ещё проверяется. Дождитесь решения администратора.',
        );
    }

    public static function tariffInactive(): self
    {
        return new self('tariff_inactive', 'Оплата временно недоступна. Попробуйте позже.');
    }

    public static function domainConflict(): self
    {
        return new self(
            'domain_conflict',
            'Домен уже занят другим активным или неактивным объявлением. Измените домен или обратитесь в поддержку.',
        );
    }

    public static function receiptEmailRequired(): self
    {
        return new self('receipt_email_required', 'Укажите email для чека.');
    }

    public static function invalidPeriod(): self
    {
        return new self('invalid_period', 'Выберите срок из доступных.');
    }

    public static function invalidAmount(): self
    {
        return new self(
            'invalid_amount',
            'Итоговая сумма должна быть больше нуля. Проверьте настройки тарифа.',
        );
    }

    public static function notPayable(): self
    {
        return new self(
            'not_payable',
            'Платёж больше нельзя продолжить. Создайте новую попытку.',
            409,
        );
    }

    public static function providerUnavailable(): self
    {
        return new self(
            'provider_unavailable',
            'Не удалось получить QR-код. Проверьте соединение и повторите попытку.',
            502,
        );
    }
}
