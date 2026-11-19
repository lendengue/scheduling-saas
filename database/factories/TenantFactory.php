<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::limit(Str::slug($name), 20, '').'-'.Str::lower(Str::random(6)),
            'timezone' => 'America/Sao_Paulo',
            'locale' => 'en',
            'currency' => 'BRL',
            'booking_settings' => Tenant::defaultBookingSettings(),
            'trial_ends_at' => now()->addDays(14),
        ];
    }
}
