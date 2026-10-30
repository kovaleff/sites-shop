<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Единый payload пользователя для SPA (data-model.md §5, contracts/auth-api.yaml).
 * Используется в GET /api/v1/user и в успешном ответе VK-callback.
 */
class SessionUserResource extends JsonResource
{
    /**
     * @return array{id: int, displayName: string, email: string|null, avatarUrl: string|null, provider: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->name,
            'email' => $this->email,
            'avatarUrl' => $this->avatar,
            'provider' => $this->provider(),
        ];
    }

    /**
     * Провайдер последнего входа: 'vk' | 'yandex' | null.
     * При равенстве времени входа приоритет у VK (data-model.md §5).
     */
    public function provider(): ?string
    {
        if ($this->vk_last_login_at === null && $this->ya_last_login_at === null) {
            return null;
        }

        if ($this->vk_last_login_at === null) {
            return 'yandex';
        }

        if ($this->ya_last_login_at === null) {
            return 'vk';
        }

        return $this->vk_last_login_at->gte($this->ya_last_login_at) ? 'vk' : 'yandex';
    }
}
