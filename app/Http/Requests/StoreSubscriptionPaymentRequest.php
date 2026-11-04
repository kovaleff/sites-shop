<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPaymentRequest extends FormRequest
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
            'periodMonths' => ['required', 'integer', 'min:1', 'max:24'],
            'receiptEmail' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periodMonths.required' => 'Выберите срок подписки.',
            'periodMonths.integer' => 'Срок подписки должен быть числом месяцев.',
            'receiptEmail.email' => 'Укажите корректный email для чека.',
            'receiptEmail.max' => 'Email для чека слишком длинный.',
        ];
    }

    public function periodMonths(): int
    {
        return (int) $this->validated('periodMonths');
    }

    /**
     * Email для чека: из запроса, иначе — доставляемый email аккаунта.
     */
    public function receiptEmail(User $user): ?string
    {
        $email = $this->validated('receiptEmail');
        $email = is_string($email) ? mb_strtolower(trim($email)) : null;

        if ($email !== null && $email !== '') {
            return $email;
        }

        return $user->hasDeliverableEmail() ? mb_strtolower(trim((string) $user->email)) : null;
    }
}
