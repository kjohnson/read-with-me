<?php

namespace Database\Factories;

use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MagicLink>
 */
class MagicLinkFactory extends Factory
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
            'token_hash' => MagicLink::hashToken(Str::random(64)),
            'expires_at' => now()->addMinutes(MagicLink::LIFETIME_MINUTES),
        ];
    }

    /**
     * Indicate that the link is for the given plain-text token.
     */
    public function forToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => MagicLink::hashToken($token),
        ]);
    }

    /**
     * Indicate that the link has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
