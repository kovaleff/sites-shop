<?php

namespace App\Enums;

enum PaymentReviewDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Принять',
            self::Rejected => 'Отклонить',
        };
    }

    public function resultStatus(): PaymentStatus
    {
        return match ($this) {
            self::Accepted => PaymentStatus::Paid,
            self::Rejected => PaymentStatus::Failed,
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
