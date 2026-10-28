<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
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
            'buyer_id' => User::factory(),
            'seller_id' => fn (array $attributes) => Site::query()
                ->findOrFail($attributes['site_id'])
                ->user_id,
            'last_message_at' => null,
        ];
    }

    public function forSite(Site $site, User $buyer): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $site->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $site->user_id,
        ]);
    }
}
