<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'name' => fake()->randomElement(['Haircut', 'Beard trim', 'Massage', 'Consultation', 'Manicure']).' '.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->optional()->sentence(),
            'duration_minutes' => 30,
            'buffer_after_minutes' => 0,
            'price_cents' => 5000,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function duration(int $minutes, int $buffer = 0): static
    {
        return $this->state(fn () => ['duration_minutes' => $minutes, 'buffer_after_minutes' => $buffer]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
