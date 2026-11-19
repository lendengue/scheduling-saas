<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+55119'.fake()->numerify('########'),
            'notes' => null,
            'marketing_opt_in' => false,
        ];
    }
}
