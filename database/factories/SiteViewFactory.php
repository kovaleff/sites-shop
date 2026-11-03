<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteView>
 */
class SiteViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'date' => today()->toDateString(),
            'count' => fake()->numberBetween(1, 100),
        ];
    }
}
