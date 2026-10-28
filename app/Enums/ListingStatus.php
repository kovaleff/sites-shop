<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Withdrawn = 'withdrawn';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Inactive => 'Неактивен',
            self::Withdrawn => 'Снят с продажи',
            self::Sold => 'Продан',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Inactive, self::Withdrawn, self::Sold],
            self::Inactive => [self::Active, self::Withdrawn, self::Sold],
            self::Withdrawn => [self::Active, self::Inactive, self::Sold],
            self::Sold => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Sold;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
