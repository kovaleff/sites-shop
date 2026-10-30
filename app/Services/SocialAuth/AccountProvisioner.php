<?php

namespace App\Services\SocialAuth;

use App\Enums\AuthProvider;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Сопоставляет профиль провайдера с аккаунтом сайта (data-model.md §3).
 *
 * Порядок: внешний ID → email (привязка к существующему аккаунту) → новый аккаунт.
 * Чужая непустая привязка (vk_id/ya_id) никогда не перезаписывается.
 */
class AccountProvisioner
{
    public function provision(ProviderProfile $profile): User
    {
        try {
            return DB::transaction(function () use ($profile): User {
                $provider = $profile->provider;

                // 1. Пользователь уже известен по внешнему ID.
                $user = $this->findByProviderId($profile, $provider);

                if ($user) {
                    return $this->refreshProfile($user, $profile, $provider);
                }

                // 2. Поиск по email из профиля (привязка к существующему аккаунту).
                $existing = null;

                if ($profile->email !== null) {
                    $existing = User::query()
                        ->whereRaw('LOWER(email) = ?', [mb_strtolower($profile->email)])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $existingProviderId = $existing->{$provider->idColumn()};

                        if ($existingProviderId === null) {
                            $this->attachProviderId($existing, $provider, $profile);

                            $user = $this->refreshProfile($existing, $profile, $provider);

                            if ($existing->hasAccess('platform.index')) {
                                Log::warning('Social login linked to admin account', [
                                    'user_id' => $existing->id,
                                    'provider' => $provider->value,
                                    'provider_id' => $profile->providerId,
                                ]);
                            }

                            return $user;
                        }

                        if ((int) $existingProviderId === (int) $profile->providerId) {
                            return $this->refreshProfile($existing, $profile, $provider);
                        }

                        // Колонка провайдера занята другим ID — не перезаписываем (FR-021).
                        // Новый аккаунт создаётся с техническим email (шаг 3 без email).
                        $profile = $profile->withoutEmail();
                    }
                }

                // 3. Новый аккаунт.
                return User::query()->create([
                    'name' => $profile->name,
                    'email' => $profile->email ?? $provider->technicalEmail((int) $profile->providerId),
                    'password' => Str::password(32),
                    $provider->idColumn() => $profile->providerId,
                    'avatar' => $profile->avatarUrl,
                    $provider->lastLoginColumn() => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Параллельный колбэк успел создать пользователя: уникальные индексы не дали дублю.
            Log::warning('Social login error', [
                'reason' => 'concurrent_login',
                'provider' => $profile->provider->value,
                'provider_id' => $profile->providerId,
                'error' => $e->getMessage(),
            ]);

            $user = $this->findByProviderId($profile, $profile->provider);

            if (! $user) {
                throw $e;
            }

            return $this->refreshProfile($user, $profile, $profile->provider);
        }
    }

    private function findByProviderId(ProviderProfile $profile): ?User
    {
        return User::query()
            ->where($profile->provider->idColumn(), $profile->providerId)
            ->first();
    }

    private function attachProviderId(User $user, AuthProvider $provider, ProviderProfile $profile): void
    {
        $user->{$provider->idColumn()} = $profile->providerId;
        $user->{$provider->lastLoginColumn()} = now();
        $user->save();
    }

    private function refreshProfile(User $user, ProviderProfile $profile, AuthProvider $provider): User
    {
        $user->name = $profile->name;

        if ($profile->email !== null && strcasecmp($profile->email, (string) $user->email) !== 0) {
            $emailTaken = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($profile->email)])
                ->whereKeyNot($user->getKey())
                ->exists();

            if (! $emailTaken) {
                $user->email = $profile->email;
            }
        }

        if ($profile->avatarUrl !== null) {
            $user->avatar = $profile->avatarUrl;
        }

        $user->{$provider->lastLoginColumn()} = now();
        $user->save();

        return $user;
    }
}
