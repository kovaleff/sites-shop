<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ошибка входа через VK ID. Короткий код причины (reason) попадает в журнал;
 * сообщение не содержит кода авторизации, токена и client_secret
 * (contracts/spa-auth-errors.md §6).
 */
class VkAuthException extends RuntimeException
{
    public const EXCHANGE_FAILED = 'exchange_failed';

    public const USER_INFO_FAILED = 'user_info_failed';

    public const PROFILE_REJECTED = 'profile_rejected';

    public function __construct(private readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }

    public static function exchangeFailed(string $message = 'Token exchange failed'): self
    {
        return new self(self::EXCHANGE_FAILED, $message);
    }

    public static function userInfoFailed(string $message = 'Failed to get user data'): self
    {
        return new self(self::USER_INFO_FAILED, $message);
    }

    public static function profileRejected(string $message = 'Profile rejected'): self
    {
        return new self(self::PROFILE_REJECTED, $message);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
