<?php

namespace App\Services\SocialAuth;

use App\Enums\AuthProvider;

/**
 * Профиль провайдера: внутренний DTO, не хранится в БД.
 * Создаётся сервисами провайдеров и передаётся в AccountProvisioner (data-model.md §2).
 */
final readonly class ProviderProfile
{
    public function __construct(
        public AuthProvider $provider,
        public string $providerId,
        public string $name,
        public ?string $email = null,
        public ?string $avatarUrl = null,
    ) {}

    public static function make(
        AuthProvider $provider,
        string $providerId,
        string $name,
        ?string $email = null,
        ?string $avatarUrl = null,
    ): self {
        return new self(
            provider: $provider,
            providerId: trim($providerId),
            name: trim($name) !== '' ? trim($name) : $provider->defaultName(),
            email: self::normalizeEmail($email),
            avatarUrl: self::normalizeUrl($avatarUrl),
        );
    }

    /**
     * Версия профиля без email: используется, когда email занят аккаунтом
     * с чужой привязкой провайдера, чтобы новый аккаунт получил технический адрес.
     */
    public function withoutEmail(): self
    {
        return new self(
            provider: $this->provider,
            providerId: $this->providerId,
            name: $this->name,
            email: null,
            avatarUrl: $this->avatarUrl,
        );
    }

    /**
     * Привести providerId к целому; вернуть null при неположительном значении.
     */
    public function intProviderId(): ?int
    {
        $value = (int) $this->providerId;

        return $value > 0 ? $value : null;
    }

    private static function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        if ($email === '' || mb_strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private static function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || mb_strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }
}
