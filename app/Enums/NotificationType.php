<?php

namespace App\Enums;

/**
 * Типы писем, которые пользователь может включать и выключать
 * в настройках уведомлений кабинета.
 */
enum NotificationType: string
{
    case Messages = 'messages';
    case Moderation = 'moderation';
    case Subscription = 'subscription';
    case Reports = 'reports';
    case Favorites = 'favorites';
    case SavedSearches = 'saved_searches';

    public function label(): string
    {
        return match ($this) {
            self::Messages => 'Новые сообщения',
            self::Moderation => 'Решения модерации',
            self::Subscription => 'Истечение подписки',
            self::Reports => 'Жалобы на объявления',
            self::Favorites => 'Избранные лоты',
            self::SavedSearches => 'Новые лоты по фильтру',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Messages => 'Письмо о новом сообщении в диалоге по вашему объявлению.',
            self::Moderation => 'Письмо, когда объявление одобрено или отклонено модератором.',
            self::Subscription => 'Напоминания за 7 и 1 день до окончания оплаченного периода.',
            self::Reports => 'Письмо, когда жалоба на ваше объявление рассмотрена.',
            self::Favorites => 'Изменение цены или продажа лота, который вы добавили в избранное.',
            self::SavedSearches => 'Дайджест новых лотов, подходящих под сохранённый фильтр.',
        };
    }

    /**
     * Настройки по умолчанию: все уведомления включены.
     *
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => true])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
