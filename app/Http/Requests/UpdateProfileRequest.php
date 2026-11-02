<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Доступ проверяется middleware `auth`.
     */
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите имя, которое увидят покупатели.',
            'name.min' => 'Имя должно быть от 2 до 255 символов.',
            'name.max' => 'Имя должно быть от 2 до 255 символов.',
            'bio.max' => 'Описание должно быть не длиннее 1000 символов.',
            'city.max' => 'Город должен быть не длиннее 100 символов.',
        ];
    }
}
