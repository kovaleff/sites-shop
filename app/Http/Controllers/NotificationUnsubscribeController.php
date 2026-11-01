<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\View\View;

/**
 * Отключение одного типа писем по ссылке из письма.
 * Маршрут защищён подписью, вход в аккаунт не требуется.
 */
class NotificationUnsubscribeController extends Controller
{
    public function __invoke(string $type, int $user): View
    {
        $notificationType = NotificationType::tryFrom($type);

        abort_if($notificationType === null, 404);

        $model = User::query()->findOrFail($user);
        $model->disableNotification($notificationType);

        return view('notifications.unsubscribed', [
            'label' => $notificationType->label(),
            'manageUrl' => url('/cabinet/settings/notifications'),
        ]);
    }
}
