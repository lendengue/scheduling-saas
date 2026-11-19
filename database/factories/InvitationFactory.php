<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * The plain token of the last invitation built by this factory, so tests
     * can build a valid acceptance link.
     */
    public static ?string $lastPlainToken = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Staff,
            'token_hash' => function (): string {
                $token = SecureToken::generate();
                self::$lastPlainToken = $token->plain;

                return $token->hash;
            },
            'expires_at' => now()->addDays(7),
        ];
    }

    public function withToken(string $plain): static
    {
        return $this->state(fn () => ['token_hash' => SecureToken::hash($plain)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
