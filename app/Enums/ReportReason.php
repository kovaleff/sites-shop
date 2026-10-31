<?php

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Fraud = 'fraud';
    case WrongInfo = 'wrong_info';
    case Forbidden = 'forbidden';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Спам или реклама',
            self::Fraud => 'Мошенничество',
            self::WrongInfo => 'Недостоверные данные',
            self::Forbidden => 'Запрещённый контент',
            self::Other => 'Другое',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason) => [$reason->value => $reason->label()])
            ->all();
    }
}
