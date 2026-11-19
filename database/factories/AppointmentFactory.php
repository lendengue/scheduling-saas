<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Support\SecureToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds appointments straight into the database, bypassing availability.
 * Use the BookAppointment action to test booking rules.
 *
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * The plain management token of the last appointment built.
     */
    public static ?string $lastPlainToken = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now()->addDays(2)->setTime(14, 0);

        return [
            'staff_profile_id' => StaffProfile::factory(),
            'tenant_id' => fn (array $attributes) => StaffProfile::withoutGlobalScopes()->whereKey($attributes['staff_profile_id'])->value('tenant_id'),
            'service_id' => fn (array $attributes) => Service::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'customer_id' => fn (array $attributes) => Customer::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
            'duration_minutes' => 30,
            'price_cents' => 5000,
            'notes' => null,
            'manage_token_hash' => function (): string {
                $token = SecureToken::generate();
                self::$lastPlainToken = $token->plain;

                return $token->hash;
            },
        ];
    }

    public function at(CarbonImmutable $startsAt, int $blockedMinutes = 30): static
    {
        return $this->state(fn () => [
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->utc()->addMinutes($blockedMinutes),
            'duration_minutes' => $blockedMinutes,
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : null,
        ]);
    }

    public function withToken(string $plain): static
    {
        return $this->state(fn () => ['manage_token_hash' => SecureToken::hash($plain)]);
    }
}
