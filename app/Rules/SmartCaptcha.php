<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

/**
 * Проверка токена Яндекс SmartCaptcha на сервере.
 *
 * Без настроенного серверного ключа проверка выключена и пропускает запрос:
 * окружение разработки и тесты не зависят от внешнего сервиса.
 */
final class SmartCaptcha implements ValidationRule
{
    private const VALIDATE_URL = 'https://smartcaptcha.yandexcloud.net/validate';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) config('sites.captcha.server_key');

        if ($secret === '') {
            return;
        }

        if (! is_string($value) || trim($value) === '') {
            $fail('Подтвердите, что вы не робот.');

            return;
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(5)
            ->post(self::VALIDATE_URL, [
                'secret' => $secret,
                'token' => $value,
                'ip' => request()->ip(),
            ]);

        if (! $response->successful() || $response->json('status') !== 'ok') {
            $fail('Проверка «я не робот» не пройдена. Попробуйте ещё раз.');
        }
    }
}
