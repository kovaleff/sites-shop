<?php

namespace App\Services;

use App\Enums\AuthProvider;
use App\Exceptions\VkAuthException;
use App\Services\SocialAuth\ProviderProfile;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Обмен полученного от VK ID authorization code на токен и профиль пользователя.
 *
 * Никакой работы с БД: результатом является ProviderProfile, который
 * в аккаунт превращает AccountProvisioner (data-model.md §2–§3).
 */
class VkAuthService
{
    private const TOKEN_URL = 'https://id.vk.ru/oauth2/auth';

    private const API_URL = 'https://api.vk.com/method/users.get';

    private const API_VERSION = '5.131';

    /**
     * Обменять authorization code на токен VK и вернуть нормализованный профиль.
     *
     * @throws VkAuthException
     */
    public function loginWithCode(
        string $code,
        string $codeVerifier,
        string $deviceId,
        string $redirectUri
    ): ProviderProfile {
        $response = Http::post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
            'code' => $code,
            'client_id' => config('services.vk.client_id'),
            'device_id' => $deviceId,
        ]);

        $data = $response->json();

        if (isset($data['error']) || ! isset($data['access_token'])) {
            Log::warning('VK OAuth error', [
                'reason' => 'exchange_failed',
                'error' => $data['error_description'] ?? $data['error'] ?? 'unknown',
            ]);

            throw VkAuthException::exchangeFailed($data['error_description'] ?? 'Token exchange failed');
        }

        $userData = $this->fetchUserInfo($data['access_token']);
        $vkUserId = $userData['id'] ?? $data['user_id'] ?? null;

        if ($vkUserId === null || (int) $vkUserId <= 0) {
            throw VkAuthException::profileRejected('VK profile has no id');
        }

        $fullName = trim(($userData['first_name'] ?? '').' '.($userData['last_name'] ?? ''));

        return ProviderProfile::make(
            provider: AuthProvider::Vk,
            providerId: (string) $vkUserId,
            name: $fullName,
            email: isset($data['email']) ? (string) $data['email'] : null,
            avatarUrl: isset($userData['photo_200']) ? (string) $userData['photo_200'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws VkAuthException
     */
    private function fetchUserInfo(string $accessToken): array
    {
        $response = Http::get(self::API_URL, [
            'access_token' => $accessToken,
            'fields' => 'photo_200,first_name,last_name',
            'v' => self::API_VERSION,
        ]);

        $data = $response instanceof Response ? $response->json() : [];

        if (isset($data['error'])) {
            Log::warning('VK API error', [
                'reason' => 'user_info_failed',
                'error' => $data['error']['error_msg'] ?? 'unknown',
            ]);

            throw VkAuthException::userInfoFailed('Failed to get user data');
        }

        return $data['response'][0] ?? [];
    }
}
