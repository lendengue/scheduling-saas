<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->staff(),
            'tenant_id' => fn (array $attributes) => User::withoutGlobalScopes()->whereKey($attributes['user_id'])->value('tenant_id'),
            'bio' => null,
            'color' => fake()->randomElement(StaffProfile::COLORS),
            'is_bookable' => true,
        ];
    }

    public function notBookable(): static
    {
        return $this->state(fn () => ['is_bookable' => false]);
    }

    /**
     * Perform the given services.
     *
     * @param  Service|iterable<Service>  $services
     */
    public function performing(Service|iterable $services): static
    {
        return $this->afterCreating(function (StaffProfile $profile) use ($services): void {
            $services = $services instanceof Service ? [$services] : $services;
            $profile->services()->attach(collect($services)->map(fn (Service $service) => $service->getKey())->all());
        });
    }

    /**
     * Work the given wall-clock interval on the given weekdays.
     *
     * @param  list<int>  $weekdays  0 = Sunday ... 6 = Saturday
     */
    public function working(string $from, string $to, array $weekdays = [0, 1, 2, 3, 4, 5, 6]): static
    {
        return $this->afterCreating(function (StaffProfile $profile) use ($from, $to, $weekdays): void {
            foreach ($weekdays as $weekday) {
                WorkingHour::withoutGlobalScopes()->create([
                    'tenant_id' => $profile->tenant_id,
                    'staff_profile_id' => $profile->id,
                    'weekday' => $weekday,
                    'starts_at' => $from,
                    'ends_at' => $to,
                ]);
            }
        });
    }
}
