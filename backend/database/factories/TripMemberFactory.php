<?php

namespace Database\Factories;

use App\Models\TripMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TripMember>
 */
class TripMemberFactory extends Factory
{
    /**
     * An active editor. Attach the trip with for($budget).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => 'editor',
            'status' => 'active',
            'joined_at' => now(),
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn (array $attributes): array => ['role' => $role]);
    }

    /**
     * An email invite that nobody has accepted yet.
     */
    public function invited(?string $email = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'email' => $email ?? fake()->unique()->safeEmail(),
            'status' => 'invited',
            'invite_token' => Str::random(40),
            'expires_at' => now()->addDays(TripMember::INVITE_DAYS),
            'joined_at' => null,
        ]);
    }
}
