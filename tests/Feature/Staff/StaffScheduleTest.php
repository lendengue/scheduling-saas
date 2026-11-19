<?php

use App\Actions\Tenants\CreateTenant;
use App\Data\Tenants\NewTenantData;
use App\Enums\Role;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'timezone' => 'America/Sao_Paulo']);
    $this->owner = User::factory()->for($this->tenant)->owner()->create();
    $this->staff = User::factory()->for($this->tenant)->staff()->create();
    $this->current = app(CurrentTenant::class);
});

afterEach(fn () => $this->current->forget());

function profileOf(User $user): StaffProfile
{
    return app(CurrentTenant::class)->runAs($user->tenant, fn () => StaffProfile::forUser($user));
}

function weekdayHours(): array
{
    return [
        'hours' => [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
            ['weekday' => 1, 'starts_at' => '13:00', 'ends_at' => '18:00'],
            ['weekday' => 2, 'starts_at' => '10:00', 'ends_at' => '16:00'],
        ],
    ];
}

describe('profile', function () {
    test('owners set role, booking availability, color and services', function () {
        $service = $this->current->runAs($this->tenant, fn () => Service::factory()->create());

        $this->actingAs($this->owner)
            ->put(tenantRoute($this->tenant, 'team.members.update', ['member' => $this->staff->id]), [
                'role' => 'admin',
                'is_bookable' => true,
                'color' => '#AA00FF',
                'bio' => 'Senior barber',
                'service_ids' => [$service->id],
            ])
            ->assertSessionHasNoErrors();

        $this->current->set($this->tenant);
        $profile = StaffProfile::forUser($this->staff);

        expect($profile->is_bookable)->toBeTrue()
            ->and($profile->color)->toBe('#aa00ff')
            ->and($profile->bio)->toBe('Senior barber')
            ->and($profile->services->modelKeys())->toBe([$service->id])
            ->and($this->staff->fresh()->tenantRole())->toBe(Role::Admin);
    });

    test("the owner's role cannot be changed", function () {
        $this->actingAs($this->owner)
            ->put(tenantRoute($this->tenant, 'team.members.update', ['member' => $this->owner->id]), [
                'role' => 'owner',
                'color' => '#000000',
            ])
            ->assertSessionHasErrors('role');
    });

    test('staff cannot change profiles, not even their own', function () {
        $this->actingAs($this->staff)
            ->put(tenantRoute($this->tenant, 'team.members.update', ['member' => $this->staff->id]), [
                'role' => 'admin',
                'color' => '#000000',
            ])
            ->assertForbidden();
    });

    test('services of another tenant cannot be assigned', function () {
        $foreign = Service::factory()->create();

        $this->actingAs($this->owner)
            ->put(tenantRoute($this->tenant, 'team.members.update', ['member' => $this->staff->id]), [
                'color' => '#000000',
                'service_ids' => [$foreign->id],
            ])
            ->assertSessionHasErrors('service_ids.0');
    });

    test('the member page shows the schedule in the tenant timezone', function () {
        $profile = profileOf($this->staff);
        TimeOff::factory()->for($profile)->create([
            'starts_at' => '2030-01-10 12:00:00',
            'ends_at' => '2030-01-10 15:00:00',
        ]);
        TimeOff::factory()->for($profile)->create([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->owner)
            ->get(tenantRoute($this->tenant, 'team.members.show', ['member' => $this->staff->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Team/Show')
                ->where('member.role', 'staff')
                ->where('can.update', true)
                ->has('timeOffs', 1)
                ->where('timeOffs.0.starts_at', '2030-01-10 09:00')
                ->where('timezone', 'America/Sao_Paulo'),
            );
    });
});

describe('working hours', function () {
    test('owners and the member themselves can set working hours', function (string $actor) {
        $user = $actor === 'owner' ? $this->owner : $this->staff;

        $this->actingAs($user)
            ->put(tenantRoute($this->tenant, 'team.members.working-hours.update', ['member' => $this->staff->id]), weekdayHours())
            ->assertSessionHasNoErrors();

        $hours = WorkingHour::withoutGlobalScopes()->orderBy('weekday')->orderBy('starts_at')->get();

        expect($hours)->toHaveCount(3)
            ->and($hours->pluck('tenant_id')->unique()->all())->toBe([$this->tenant->id])
            ->and($hours[1]->starts_at)->toBe('13:00:00');
    })->with(['owner', 'self']);

    test('saving replaces the previous schedule', function () {
        $this->actingAs($this->owner)
            ->put(tenantRoute($this->tenant, 'team.members.working-hours.update', ['member' => $this->staff->id]), weekdayHours());
        $this->actingAs($this->owner)
            ->put(tenantRoute($this->tenant, 'team.members.working-hours.update', ['member' => $this->staff->id]), ['hours' => []])
            ->assertSessionHasNoErrors();

        expect(WorkingHour::withoutGlobalScopes()->count())->toBe(0);
    });

    test('staff cannot change the hours of a colleague', function () {
        $colleague = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($this->staff)
            ->put(tenantRoute($this->tenant, 'team.members.working-hours.update', ['member' => $colleague->id]), weekdayHours())
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->get(tenantRoute($this->tenant, 'team.members.show', ['member' => $colleague->id]))
            ->assertForbidden();
    });

    test('invalid intervals are rejected', function (array $hours, string $error) {
        $this->actingAs($this->owner)
            ->put(tenantRoute($this->tenant, 'team.members.working-hours.update', ['member' => $this->staff->id]), ['hours' => $hours])
            ->assertSessionHasErrors($error);
    })->with([
        'end before start' => [[['weekday' => 1, 'starts_at' => '18:00', 'ends_at' => '09:00']], 'hours.0.ends_at'],
        'bad weekday' => [[['weekday' => 7, 'starts_at' => '09:00', 'ends_at' => '10:00']], 'hours.0.weekday'],
        'overlap' => [[
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
            ['weekday' => 1, 'starts_at' => '11:00', 'ends_at' => '13:00'],
        ], 'hours'],
    ]);
});

describe('time off', function () {
    test('time off is entered in tenant time and stored in UTC', function () {
        $this->actingAs($this->staff)
            ->post(tenantRoute($this->tenant, 'team.members.time-offs.store', ['member' => $this->staff->id]), [
                'starts_at' => '2030-03-04T13:00',
                'ends_at' => '2030-03-04T15:30',
                'reason' => 'Dentist',
            ])
            ->assertSessionHasNoErrors();

        $timeOff = TimeOff::withoutGlobalScopes()->sole();

        expect($timeOff->starts_at->toIso8601String())->toBe('2030-03-04T16:00:00+00:00')
            ->and($timeOff->ends_at->toIso8601String())->toBe('2030-03-04T18:30:00+00:00')
            ->and($timeOff->reason)->toBe('Dentist');
    });

    test('the end must come after the start', function () {
        $this->actingAs($this->owner)
            ->post(tenantRoute($this->tenant, 'team.members.time-offs.store', ['member' => $this->staff->id]), [
                'starts_at' => '2030-03-04T13:00',
                'ends_at' => '2030-03-04T12:00',
            ])
            ->assertSessionHasErrors('ends_at');
    });

    test('time off can be removed only through its own member', function () {
        $colleague = User::factory()->for($this->tenant)->staff()->create();
        $timeOff = TimeOff::factory()->for(profileOf($colleague))->create();

        $this->actingAs($this->owner)
            ->delete(tenantRoute($this->tenant, 'team.members.time-offs.destroy', ['member' => $this->staff->id, 'timeOff' => $timeOff->id]))
            ->assertNotFound();

        $this->actingAs($this->staff)
            ->delete(tenantRoute($this->tenant, 'team.members.time-offs.destroy', ['member' => $colleague->id, 'timeOff' => $timeOff->id]))
            ->assertForbidden();

        $this->actingAs($colleague)
            ->delete(tenantRoute($this->tenant, 'team.members.time-offs.destroy', ['member' => $colleague->id, 'timeOff' => $timeOff->id]))
            ->assertRedirect();

        expect(TimeOff::withoutGlobalScopes()->count())->toBe(0);
    });
});

test('new owners take bookings by default; invited admins do not', function () {
    $created = app(CreateTenant::class)->handle(new NewTenantData(
        businessName: 'Solo Barber',
        slug: 'solo',
        timezone: 'America/Sao_Paulo',
        locale: 'en',
        ownerName: 'Sam Solo',
        ownerEmail: 'sam@solo.test',
        ownerPassword: 'password',
    ));

    $this->current->set($created->tenant);
    expect(StaffProfile::forUser($created->owner)->is_bookable)->toBeTrue();
});
