<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Sbp = 'sbp';

    public function label(): string
    {
        return match ($this) {
            self::Sbp => 'СБП',
        };
    }

    /**
     * Обозначение способа оплаты на стороне Robokassa.
     */
    public function providerCode(): string
    {
        return match ($this) {
            self::Sbp => 'SBP',
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
