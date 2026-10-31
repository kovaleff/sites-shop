<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Site;
use App\Models\SiteReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteReport>
 */
class SiteReportFactory extends Factory
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
            'user_id' => User::factory(),
            'reason' => ReportReason::Spam,
            'comment' => fake()->optional()->sentence(),
            'status' => ReportStatus::New,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
