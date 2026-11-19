<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->startOfHour();

        return [
            'staff_profile_id' => StaffProfile::factory(),
            'tenant_id' => fn (array $attributes) => StaffProfile::withoutGlobalScopes()->whereKey($attributes['staff_profile_id'])->value('tenant_id'),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'reason' => null,
        ];
    }
}
