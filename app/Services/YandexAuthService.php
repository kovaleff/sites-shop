<?php

namespace App\Services;

use App\Enums\AuthProvider;
use App\Exceptions\YandexAuthException;
use App\Services\SocialAuth\ProviderProfile;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Превращает профиль Яндекса (Socialite) в ProviderProfile.
 *
 * Никакой работы с БД: в аккаунт профиль превращает AccountProvisioner
 * (data-model.md §2–§3). Ссылку на фото храним как есть, без копирования (FR-028).
 */
class YandexAuthService
{
    public const DEFAULT_NAME = 'Пользователь Яндекса';

    public function profileFromSocialite(SocialiteUser $profile): ProviderProfile
    {
        $yaId = (int) $profile->getId();

        if ($yaId <= 0) {
            throw YandexAuthException::profileRejected('Yandex profile has no id');
        }

        $raw = $profile instanceof AbstractUser ? $profile->getRaw() : [];

        $name = $this->firstFilled([
            $profile->getName(),
            $raw['display_name'] ?? null,
            $profile->getNickname(),
        ]) ?? self::DEFAULT_NAME;

        $email = $profile->getEmail() ?? ($raw['default_email'] ?? null);

        return ProviderProfile::make(
            provider: AuthProvider::Yandex,
            providerId: (string) $yaId,
            name: $name,
            email: $email !== null ? (string) $email : null,
            avatarUrl: $this->avatarUrl($raw),
        );
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function avatarUrl(array $raw): ?string
    {
        $avatarId = trim((string) ($raw['default_avatar_id'] ?? ''));
        $isEmpty = filter_var($raw['is_avatar_empty'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Провайдер строит ссылку безусловно, поэтому пустой id даёт битый URL.
        if ($isEmpty || $avatarId === '') {
            return null;
        }

        return 'https://avatars.yandex.net/get-yapic/'.$avatarId.'/islands-200';
    }
}
