<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user signs in through VK ID.
     */
    public function vk(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'password' => null,
            'avatar' => fake()->imageUrl(),
            'vk_id' => fake()->unique()->numberBetween(1, 999999999),
            'vk_last_login_at' => now(),
        ]);
    }

    /**
     * Indicate that the user signs in through Yandex ID.
     */
    public function yandex(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'password' => null,
            'avatar' => fake()->imageUrl(),
            'ya_id' => fake()->unique()->numberBetween(1, 999999999),
            'ya_last_login_at' => now(),
        ]);
    }
}
