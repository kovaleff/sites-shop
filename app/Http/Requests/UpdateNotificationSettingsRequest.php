<?php

namespace App\Http\Requests;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:'.implode(',', NotificationType::values())],
            'settings.*' => ['boolean'],
        ];
    }
}
