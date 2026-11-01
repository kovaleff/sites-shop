<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    /**
     * Настройки email-уведомлений пользователя.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    /**
     * Включить или выключить отдельные типы писем.
     */
    public function update(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->updateNotificationSettings($request->validated()['settings']);

        return response()->json($this->payload($user->refresh()));
    }

    /**
     * @return array{
     *     data: array<int, array{value: string, label: string, description: string, enabled: bool}>,
     *     email: string|null
     * }
     */
    private function payload(User $user): array
    {
        return [
            'data' => array_map(fn (NotificationType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'enabled' => $user->wantsNotification($type),
            ], NotificationType::cases()),
            'email' => $user->hasDeliverableEmail() ? $user->email : null,
        ];
    }
}
