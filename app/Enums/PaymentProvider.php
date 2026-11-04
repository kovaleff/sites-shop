<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Robokassa = 'robokassa';

    public function label(): string
    {
        return match ($this) {
            self::Robokassa => 'Robokassa',
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
