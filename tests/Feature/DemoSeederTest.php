<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;

afterEach(fn () => app(CurrentTenant::class)->forget());

test('the demo tenant has a team, a catalog, customers and two weeks of bookings', function () {
    $this->seed(DemoSeeder::class);

    $tenant = Tenant::query()->where('slug', 'demo')->sole();

    app(CurrentTenant::class)->runAs($tenant, function () {
        $now = CarbonImmutable::now();

        expect(StaffProfile::query()->bookable()->count())->toBe(3)
            ->and(Service::query()->count())->toBe(5)
            ->and(Customer::query()->count())->toBe(30)
            ->and(Appointment::query()->where('starts_at', '>', $now)->blocking()->count())->toBeGreaterThan(50)
            ->and(Appointment::query()->where('starts_at', '>', $now->addDays(15))->count())->toBe(0)
            ->and(Appointment::query()->where('starts_at', '<', $now)->where('status', AppointmentStatus::Completed)->count())->toBeGreaterThan(0);
    });
});
