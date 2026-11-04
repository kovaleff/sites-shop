<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

/**
 * Фильтр по одному или нескольким доменам.
 *
 * Ищет и по домену на момент счёта (`site_domain`), и по текущему домену
 * объявления, поэтому находит платежи после смены домена.
 */
class PaymentDomainsFilter extends Filter
{
    public function name(): string
    {
        return 'Домены';
    }

    /**
     * @return array<int, string>
     */
    public function parameters(): array
    {
        return ['domains'];
    }

    public function run(Builder $builder): Builder
    {
        $domains = $this->domains();

        if ($domains === []) {
            return $builder;
        }

        return $builder->where(function (Builder $query) use ($domains): void {
            $query->whereIn('site_domain', $domains)
                ->orWhereHas('site', function (Builder $siteQuery) use ($domains): void {
                    $siteQuery->whereIn('domain', $domains);
                });
        });
    }

    public function display(): array
    {
        return [
            Input::make('domains')
                ->title('Домены')
                ->placeholder('example.ru, second.ru')
                ->value($this->request->get('domains')),
        ];
    }

    public function value(): string
    {
        return $this->name().': '.implode(', ', $this->domains());
    }

    /**
     * @return array<int, string>
     */
    private function domains(): array
    {
        $raw = (string) $this->request->get('domains');

        return collect(preg_split('/[\s,;]+/u', $raw) ?: [])
            ->map(fn (string $domain): ?string => Site::normalizeDomain($domain))
            ->filter(fn (?string $domain): bool => $domain !== null && $domain !== '')
            ->unique()
            ->values()
            ->all();
    }
}
