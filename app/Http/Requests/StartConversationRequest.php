<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'siteId' => ['required', 'integer'],
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'siteId.required' => 'Объявление не найдено.',
            'siteId.integer' => 'Объявление не найдено.',
            'body.required' => 'Введите сообщение.',
            'body.max' => 'Сообщение должно быть не длиннее 2000 символов.',
        ];
    }
}
