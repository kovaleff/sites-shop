<?php

namespace App\Http\Requests;

use App\Enums\SavedSearchFrequency;
use App\Services\Catalog\CatalogFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedSearchRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:100'],
            'frequency' => ['required', 'string', Rule::in(SavedSearchFrequency::values())],
            'filters' => ['required', 'array'],
            'filters.price_min' => ['nullable', 'integer', 'min:0', 'max:'.CatalogFilter::PRICE_MAX],
            'filters.price_max' => ['nullable', 'integer', 'min:0', 'max:'.CatalogFilter::PRICE_MAX],
            'filters.income_min' => ['nullable', 'integer', 'min:0', 'max:'.CatalogFilter::INCOME_MAX],
            'filters.income_max' => ['nullable', 'integer', 'min:0', 'max:'.CatalogFilter::INCOME_MAX],
            'filters.zones' => ['nullable', 'array', 'max:50'],
            'filters.zones.*' => ['string', 'max:32'],
            'filters.categories' => ['nullable', 'array', 'max:50'],
            'filters.categories.*' => ['string', 'max:100'],
            'filters.verified' => ['nullable', 'boolean'],
            'filters.q' => ['nullable', 'string', 'max:100'],
            'filters.sort' => ['nullable', 'string', Rule::in(CatalogFilter::SORT_KEYS)],
        ];
    }
}
