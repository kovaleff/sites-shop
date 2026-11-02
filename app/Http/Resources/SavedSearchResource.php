<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use App\Services\Catalog\CatalogFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Сохранённый фильтр кабинета.
 *
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'frequency' => $this->frequency->value,
            'frequencyLabel' => $this->frequency->label(),
            'filters' => $this->filters,
            'summary' => $this->summary(),
            'url' => $this->catalogPath(),
            'lastNotifiedAt' => $this->last_notified_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Человекочитаемое описание фильтра для карточки в кабинете.
     */
    private function summary(): string
    {
        $filters = app(CatalogFilter::class)->normalize($this->filters ?? []);
        $parts = [];

        if ($filters['price_min'] > 0 || $filters['price_max'] < CatalogFilter::PRICE_MAX) {
            $parts[] = 'Цена: '.$this->formatRange($filters['price_min'], $filters['price_max']);
        }

        if ($filters['income_min'] > 0 || $filters['income_max'] < CatalogFilter::INCOME_MAX) {
            $parts[] = 'Доход: '.$this->formatRange($filters['income_min'], $filters['income_max']);
        }

        if ($filters['zones'] !== []) {
            $parts[] = 'Зоны: '.implode(', ', array_map(
                fn (string $zone): string => '.'.$zone,
                $filters['zones'],
            ));
        }

        if ($filters['categories'] !== []) {
            $parts[] = 'Тематик: '.count($filters['categories']);
        }

        if ($filters['verified']) {
            $parts[] = 'Только проверенные';
        }

        if ($filters['q'] !== null) {
            $parts[] = 'Поиск: «'.$filters['q'].'»';
        }

        return $parts === [] ? 'Все новые лоты' : implode(' · ', $parts);
    }

    private function formatRange(int $min, int $max): string
    {
        if ($min > 0 && $max < CatalogFilter::PRICE_MAX) {
            return $this->formatMoney($min).'–'.$this->formatMoney($max);
        }

        if ($min > 0) {
            return 'от '.$this->formatMoney($min);
        }

        return 'до '.$this->formatMoney($max);
    }

    private function formatMoney(int $value): string
    {
        return number_format($value, 0, ',', ' ').' ₽';
    }
}
