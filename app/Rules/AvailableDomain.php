<?php

namespace App\Rules;

use App\Enums\ListingStatus;
use App\Models\Site;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Домен не должен принадлежать другому активному или неактивному объявлению
 * (FR-009). Сравнение идёт по нормализованному значению.
 */
class AvailableDomain implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreSiteId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = Site::normalizeDomain(is_string($value) ? $value : null);

        if ($domain === null || $domain === '') {
            $fail('Укажите домен.');

            return;
        }

        $isTaken = Site::query()
            ->where('domain', $domain)
            ->whereIn('status', [ListingStatus::Active->value, ListingStatus::Inactive->value])
            ->when(
                $this->ignoreSiteId !== null,
                fn ($query) => $query->whereKeyNot($this->ignoreSiteId),
            )
            ->exists();

        if ($isTaken) {
            $fail('Этот домен уже занят другим объявлением.');
        }
    }
}
