<?php

namespace Database\Factories;

use App\Enums\ModerationStatus;
use App\Models\Review;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory()->sold()->paid(),
            'author_id' => User::factory(),
            'seller_id' => fn (array $attributes) => Site::query()
                ->findOrFail($attributes['site_id'])
                ->user_id,
            'rating' => fake()->numberBetween(Review::MIN_RATING, Review::MAX_RATING),
            'comment' => fake()->realText(160),
            'moderation_status' => ModerationStatus::Pending,
            'published_at' => null,
        ];
    }

    public function forSite(Site $site, User $author): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $site->id,
            'author_id' => $author->id,
            'seller_id' => $site->user_id,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => ModerationStatus::Approved,
            'published_at' => now(),
            'moderated_at' => now(),
        ]);
    }

    public function rejected(?string $note = 'Не соответствует правилам площадки.'): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => ModerationStatus::Rejected,
            'moderation_note' => $note,
            'moderated_at' => now(),
            'published_at' => null,
        ]);
    }
}
