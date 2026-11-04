<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RequiresReview = 'requires_review';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ожидает оплаты',
            self::Paid => 'Оплачен',
            self::Failed => 'Не прошёл',
            self::Cancelled => 'Отменён',
            self::RequiresReview => 'Требует проверки',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Paid;
    }

    /**
     * Открытая попытка: блокирует новую оплату по объявлению.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::RequiresReview;
    }

    /**
     * Обратная совместимость: статус, который больше не меняется сам по себе.
     * Позднее подтверждение оплаты по `failed`/`cancelled` обрабатывается
     * явными переходами `canTransitionTo()`, а не через этот метод.
     */
    public function isFinal(): bool
    {
        return $this !== self::Pending && $this !== self::RequiresReview;
    }

    /**
     * Разрешён ли переход из текущего статуса в целевой.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Failed, self::Cancelled, self::RequiresReview],
            self::RequiresReview => [self::Paid, self::Failed],
            // Позднее корректное подтверждение после истечения QR или отказа.
            self::Failed => [self::Paid, self::RequiresReview],
            self::Cancelled => [self::Paid, self::RequiresReview],
            self::Paid => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
