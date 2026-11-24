<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Carbon\CarbonImmutable;
use Faker\Factory as Faker;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo tenant "demo": a fictional barbershop with one user per role, three
 * professionals, five services, 30 customers and a realistic agenda (the
 * last week, mostly completed, and the next two weeks).
 *
 * All demo users share the password "password". The seed is fixed, so every
 * run produces the same data relative to today.
 */
class DemoSeeder extends Seeder
{
    private const string TIMEZONE = 'America/Sao_Paulo';

    private Generator $faker;

    public function run(CurrentTenant $currentTenant): void
    {
        $this->faker = Faker::create('pt_BR');
        $this->faker->seed(2026);
        mt_srand(2026);

        $tenant = Tenant::factory()->create([
            'name' => 'Demo Barbershop',
            'slug' => 'demo',
            'timezone' => self::TIMEZONE,
            'locale' => 'en',
            'currency' => 'BRL',
        ]);

        $currentTenant->runAs($tenant, function () {
            $users = $this->users();
            $services = $this->services();
            $professionals = $this->professionals($users, $services);
            $customers = $this->customers();

            $this->appointments($professionals, $services, $customers);
        });
    }

    /**
     * @return array<string, User>
     */
    private function users(): array
    {
        $users = [];

        foreach ([
            'owner' => [Role::Owner, 'Olivia Owner', 'owner@demo.test'],
            'admin' => [Role::Admin, 'Adam Admin', 'admin@demo.test'],
            'staff' => [Role::Staff, 'Sofia Staff', 'staff@demo.test'],
            'rafael' => [Role::Staff, 'Rafael Souza', 'rafael@demo.test'],
        ] as $key => [$role, $name, $email]) {
            $users[$key] = User::factory()->withRole($role)->create(['name' => $name, 'email' => $email]);
        }

        return $users;
    }

    /**
     * @return Collection<int, Service>
     */
    private function services(): Collection
    {
        return collect([
            ['Haircut', 'Classic or modern cut, washed and styled.', 30, 0, 5000],
            ['Beard trim', 'Shaping and trimming with a hot towel finish.', 20, 10, 3500],
            ['Haircut + beard', 'The full treatment.', 50, 10, 7500],
            ['Kids haircut', 'For children up to 12 years old.', 30, 0, 4000],
            ['Hot towel shave', 'Traditional straight razor shave.', 40, 5, 5500],
        ])->map(function (array $service, int $position): Service {
            [$name, $description, $duration, $buffer, $price] = $service;

            $model = Service::create([
                'name' => $name,
                'description' => $description,
                'duration_minutes' => $duration,
                'buffer_after_minutes' => $buffer,
                'price_cents' => $price,
                'is_active' => true,
            ]);
            $model->forceFill(['position' => $position])->save();

            return $model;
        });
    }

    /**
     * The owner and two staff members take bookings; the admin does not.
     *
     * @param  array<string, User>  $users
     * @param  Collection<int, Service>  $services
     * @return Collection<int, StaffProfile>
     */
    private function professionals(array $users, Collection $services): Collection
    {
        $schedules = [
            // weekday => intervals; 0 = Sunday.
            'owner' => [2, 3, 4, 5, 6],
            'staff' => [1, 2, 3, 4, 5],
            'rafael' => [2, 3, 4, 5, 6],
        ];
        $bios = [
            'owner' => 'Founder of the shop, 15 years behind the chair.',
            'staff' => 'Specialist in modern cuts and fades.',
            'rafael' => 'Beards and traditional shaves.',
        ];

        return collect($schedules)->map(function (array $weekdays, string $key) use ($users, $services, $bios): StaffProfile {
            $profile = StaffProfile::forUser($users[$key], bookable: true);
            $profile->update(['bio' => $bios[$key]]);
            // Rafael does not cut kids' hair.
            $profile->services()->sync(
                $services->reject(fn (Service $service) => $key === 'rafael' && $service->name === 'Kids haircut')->pluck('id')->all(),
            );

            foreach ($weekdays as $weekday) {
                $profile->workingHours()->createMany([
                    ['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                    ['weekday' => $weekday, 'starts_at' => '13:00', 'ends_at' => '19:00'],
                ]);
            }

            return $profile->load(['workingHours', 'services']);
        })->values();
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(): Collection
    {
        return collect(range(1, 30))->map(function (): Customer {
            $name = $this->faker->name();

            return Customer::create([
                'name' => $name,
                'email' => Str::slug($name, '.').'.'.$this->faker->unique()->numberBetween(10, 999).'@example.com',
                'phone' => '+55'.$this->faker->randomElement(['11', '21', '31', '41', '51']).'9'.$this->faker->numerify('########'),
                'marketing_opt_in' => $this->faker->boolean(40),
            ]);
        });
    }

    /**
     * Fills each working interval from 7 days ago to 14 days ahead, leaving
     * gaps so the public page still offers free slots.
     *
     * @param  Collection<int, StaffProfile>  $professionals
     * @param  Collection<int, Service>  $services
     * @param  Collection<int, Customer>  $customers
     */
    private function appointments(Collection $professionals, Collection $services, Collection $customers): void
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $today = $now->startOfDay();

        foreach (range(-7, 14) as $offset) {
            $day = $today->addDays($offset);

            foreach ($professionals as $profile) {
                $intervals = $profile->workingHours->where('weekday', $day->dayOfWeek);
                $offered = $profile->services;

                foreach ($intervals as $interval) {
                    $cursor = $day->setTimeFromTimeString($interval->starts_at);
                    $end = $day->setTimeFromTimeString($interval->ends_at);

                    while ($cursor->lt($end)) {
                        /** @var Service $service */
                        $service = $offered->random();
                        $blockedEnd = $cursor->addMinutes($service->blockedMinutes());

                        // Busier in the past than in the future, which is still filling up.
                        $occupancy = $offset < 0 ? 75 : max(20, 65 - $offset * 3);

                        if ($blockedEnd->gt($end) || mt_rand(1, 100) > $occupancy) {
                            $cursor = $cursor->addMinutes(30);

                            continue;
                        }

                        $this->book($profile, $service, $customers->random(), $cursor, $now);
                        $cursor = $blockedEnd;
                    }
                }
            }
        }
    }

    private function book(StaffProfile $profile, Service $service, Customer $customer, CarbonImmutable $startsAt, CarbonImmutable $now): void
    {
        $status = $this->statusFor($startsAt, $now);

        $appointment = new Appointment;
        $appointment->forceFill([
            'staff_profile_id' => $profile->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addMinutes($service->blockedMinutes())->utc(),
            'status' => $status,
            'duration_minutes' => $service->duration_minutes,
            'price_cents' => $service->price_cents,
            'notes' => mt_rand(1, 100) <= 10 ? $this->faker->randomElement(['First visit.', 'Prefers scissors only.', 'Allergic to some products, ask first.']) : null,
            'manage_token_hash' => SecureToken::generate()->hash,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? $startsAt->subDay()->utc() : null,
            'cancellation_reason' => $status === AppointmentStatus::Cancelled ? 'Customer could not make it.' : null,
            'reminder_sent_at' => $startsAt->isBefore($now) ? $startsAt->subDay()->utc() : null,
        ])->save();
    }

    private function statusFor(CarbonImmutable $startsAt, CarbonImmutable $now): AppointmentStatus
    {
        $roll = mt_rand(1, 100);

        if ($startsAt->isBefore($now)) {
            return match (true) {
                $roll <= 80 => AppointmentStatus::Completed,
                $roll <= 90 => AppointmentStatus::NoShow,
                default => AppointmentStatus::Cancelled,
            };
        }

        return match (true) {
            $roll <= 85 => AppointmentStatus::Confirmed,
            $roll <= 93 => AppointmentStatus::Pending,
            default => AppointmentStatus::Cancelled,
        };
    }
}
