<?php

namespace App\Enums;

/**
 * Как часто присылать письмо о новых лотах, подходящих под сохранённый фильтр.
 */
enum SavedSearchFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Раз в день',
            self::Weekly => 'Раз в неделю',
        };
    }

    public function intervalDays(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
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
