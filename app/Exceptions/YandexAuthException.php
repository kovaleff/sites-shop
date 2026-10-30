<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ошибка входа через Яндекс ID. Короткий код причины (reason) попадает
 * в журнал и определяет код auth_error для SPA; сообщение не содержит
 * кода авторизации, токена и client_secret (contracts/spa-auth-errors.md §6).
 */
class YandexAuthException extends RuntimeException
{
    public const INVALID_STATE = 'invalid_state';

    public const EXCHANGE_FAILED = 'exchange_failed';

    public const PROFILE_REJECTED = 'profile_rejected';

    public function __construct(private readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }

    public static function invalidState(string $message = 'Invalid state'): self
    {
        return new self(self::INVALID_STATE, $message);
    }

    public static function exchangeFailed(string $message = 'Token exchange failed'): self
    {
        return new self(self::EXCHANGE_FAILED, $message);
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
