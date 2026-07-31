<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'nickname' => null,
            'permissions' => [],
            'token' => Invitation::hashToken(Str::random(64)),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function withPlainToken(string $plainToken): static
    {
        return $this->state(fn (): array => [
            'token' => Invitation::hashToken($plainToken),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'accepted_at' => now(),
        ]);
    }
}
