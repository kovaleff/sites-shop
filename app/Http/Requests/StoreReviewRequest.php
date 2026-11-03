<?php

namespace App\Http\Requests;

use App\Models\Review;
use App\Rules\NoAdvertisingContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:'.Review::MIN_RATING, 'max:'.Review::MAX_RATING],
            'comment' => [
                'nullable',
                'string',
                'min:10',
                'max:'.Review::COMMENT_MAX,
                new NoAdvertisingContent,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Поставьте оценку от 1 до 5.',
            'rating.min' => 'Оценка должна быть от 1 до 5.',
            'rating.max' => 'Оценка должна быть от 1 до 5.',
            'comment.min' => 'Комментарий слишком короткий — минимум 10 символов.',
            'comment.max' => 'Комментарий длиннее '.Review::COMMENT_MAX.' символов.',
        ];
    }
}
