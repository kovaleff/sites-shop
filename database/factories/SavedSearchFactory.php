<?php

namespace Database\Factories;

use App\Enums\SavedSearchFrequency;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Catalog\CatalogFilter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'filters' => app(CatalogFilter::class)->defaults(),
            'frequency' => SavedSearchFrequency::Daily,
            'last_notified_at' => null,
        ];
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_notified_at' => now()->subDays(2),
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'frequency' => SavedSearchFrequency::Weekly,
            'last_notified_at' => now()->subDays(8),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): static
    {
        return $this->state(fn (array $attributes): array => [
            'filters' => app(CatalogFilter::class)->normalize($filters),
        ]);
    }
}
