<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_profile_id' => StaffProfile::factory(),
            'tenant_id' => fn (array $attributes) => StaffProfile::withoutGlobalScopes()->whereKey($attributes['staff_profile_id'])->value('tenant_id'),
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ];
    }
}
