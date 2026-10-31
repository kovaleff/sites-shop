<?php

namespace App\Http\Requests;

use App\Rules\AvailableDomain;
use App\Rules\NoAdvertisingContent;
use App\Rules\SmartCaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSiteRequest extends FormRequest
{
    /**
     * Доступ проверяется middleware `auth` и выборкой по владельцу.
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
            'title' => ['required', 'string', 'min:3', 'max:255', new NoAdvertisingContent],
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
                new AvailableDomain($this->ignoreSiteId()),
            ],
            'description' => ['required', 'string', 'min:50', 'max:5000', new NoAdvertisingContent],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'monthlyRevenue' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'hasTrademark' => ['sometimes', 'boolean'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
    }

    /**
     * Капча проверяется отдельно: поле может отсутствовать в запросе,
     * а обычное правило на отсутствующее поле не запускается.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        if (! $this->shouldVerifyCaptcha()) {
            return [];
        }

        return [
            function (Validator $validator): void {
                (new SmartCaptcha)->validate(
                    'captchaToken',
                    $this->input('captchaToken'),
                    fn (string $message) => $validator->errors()->add('captchaToken', $message),
                );
            },
        ];
    }

    protected function shouldVerifyCaptcha(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название объявления.',
            'title.min' => 'Название должно быть от 3 до 255 символов.',
            'title.max' => 'Название должно быть от 3 до 255 символов.',
            'domain.required' => 'Укажите домен.',
            'domain.regex' => 'Укажите домен в формате example.ru.',
            'description.required' => 'Добавьте описание объявления.',
            'description.min' => 'Описание должно быть от 50 до 5000 символов.',
            'description.max' => 'Описание должно быть от 50 до 5000 символов.',
            'price.required' => 'Укажите цену.',
            'price.min' => 'Цена не может быть отрицательной.',
            'monthlyRevenue.required' => 'Укажите месячный доход.',
            'monthlyRevenue.min' => 'Доход не может быть отрицательным.',
            'categories.required' => 'Выберите хотя бы одну тематику.',
            'categories.min' => 'Выберите хотя бы одну тематику.',
        ];
    }

    protected function ignoreSiteId(): ?int
    {
        return null;
    }
}
