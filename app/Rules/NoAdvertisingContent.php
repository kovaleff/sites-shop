<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Отсекает рекламный мусор в названии и описании объявления:
 * ссылки, телефоны, мессенджеры, запрещённые тематики и КАПС.
 */
final class NoAdvertisingContent implements ValidationRule
{
    /**
     * @var array<int, string>
     */
    private const LINK_PATTERNS = [
        '/(?:https?:\/\/|www\.)/iu',
    ];

    /**
     * @var array<int, string>
     */
    private const CONTACT_PATTERNS = [
        '/(?:\+7|8)[\s\-()]*\d{3}[\s\-()]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}/u',
        '/\+\d[\d\s\-()]{9,}\d/u',
        '/(?:^|[\s(])@[a-z0-9_]{4,}/iu',
        '/\b(?:telegram|whatsapp|телеграм|ватсап|вацап|вотсап)\b/iu',
    ];

    /**
     * @var array<int, string>
     */
    private const FORBIDDEN_PHRASES = [
        'казино',
        'букмекер',
        'ставки на спорт',
        'микрозайм',
        'интим',
        'порно',
        '18+',
        '1xbet',
        'winline',
        'mostbet',
        'vulkan',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = is_string($value) ? $value : '';

        if ($text === '') {
            return;
        }

        if ($this->matchesAny($text, self::LINK_PATTERNS)) {
            $fail('Уберите ссылки из текста: домен указывается в отдельном поле.');

            return;
        }

        if ($this->matchesAny($text, self::CONTACT_PATTERNS)) {
            $fail('Уберите телефоны и контакты мессенджеров из текста.');

            return;
        }

        if ($this->containsAny($text, self::FORBIDDEN_PHRASES)) {
            $fail('Текст содержит рекламу запрещённой тематики.');
        }
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $phrases
     */
    private function containsAny(string $text, array $phrases): bool
    {
        $haystack = mb_strtolower($text);

        foreach ($phrases as $phrase) {
            if (str_contains($haystack, $phrase)) {
                return true;
            }
        }

        return false;
    }
}
