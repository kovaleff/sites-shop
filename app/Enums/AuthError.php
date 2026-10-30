<?php

namespace App\Enums;

/**
 * Единственный источник кодов ошибок входа и их русских сообщений
 * (plan.md §4, contracts/spa-auth-errors.md). Сообщения не содержат
 * технических деталей: кодов, параметров, токенов, текстов исключений.
 */
enum AuthError: string
{
    case TooManyRequests = 'too_many_requests';
    case NotConfigured = 'not_configured';
    case VkNotConfigured = 'vk_not_configured';
    case Cancelled = 'cancelled';
    case VkCancelled = 'vk_cancelled';
    case State = 'state';
    case Failed = 'failed';
    case VkFailed = 'vk_failed';

    public function message(): string
    {
        return match ($this) {
            self::TooManyRequests => 'Слишком много попыток входа. Попробуйте позже',
            self::NotConfigured => 'Вход через Яндекс пока не настроен',
            self::VkNotConfigured => 'Вход через VK ID пока не настроен',
            self::Cancelled => 'Вход через Яндекс отменён',
            self::VkCancelled => 'Вход через VK ID отменён',
            self::State => 'Не удалось подтвердить вход через Яндекс. Попробуйте ещё раз',
            self::Failed => 'Не удалось войти через Яндекс. Попробуйте ещё раз',
            self::VkFailed => 'Не удалось войти через VK ID. Попробуйте ещё раз',
        };
    }
}
