<?php

namespace App\Enums;

/**
 * Провайдер входа: определяет колонку ID, домен технического email,
 * колонку времени входа и имя по умолчанию (data-model.md §2).
 */
enum AuthProvider: string
{
    case Vk = 'vk';
    case Yandex = 'yandex';

    public function idColumn(): string
    {
        return match ($this) {
            self::Vk => 'vk_id',
            self::Yandex => 'ya_id',
        };
    }

    public function lastLoginColumn(): string
    {
        return match ($this) {
            self::Vk => 'vk_last_login_at',
            self::Yandex => 'ya_last_login_at',
        };
    }

    public function defaultName(): string
    {
        return match ($this) {
            self::Vk => 'VK User',
            self::Yandex => 'Пользователь Яндекса',
        };
    }

    public function technicalEmail(int $providerId): string
    {
        return match ($this) {
            self::Vk => sprintf('vk%d@vk.users', $providerId),
            self::Yandex => sprintf('ya%d@ya.users', $providerId),
        };
    }
}
