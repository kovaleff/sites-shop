<?php

namespace App\Http\Requests;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteReportRequest extends FormRequest
{
    /**
     * Доступ проверяется middleware `auth`; ограничения — в контроллере.
     */
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
            'reason' => ['required', 'string', Rule::enum(ReportReason::class)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Укажите причину жалобы.',
            'reason.enum' => 'Неизвестная причина жалобы.',
            'comment.max' => 'Комментарий не должен превышать 1000 символов.',
        ];
    }
}
