<?php

use App\Actions\Tenants\CreateTenant;
use App\Data\Tenants\NewTenantData;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $created = app(CreateTenant::class)->handle(new NewTenantData(
        businessName: 'Acme Cuts',
        slug: 'acme',
        timezone: 'America/Sao_Paulo',
        ownerName: 'Olivia',
        ownerEmail: 'olivia@example.com',
        ownerPassword: 'password',
    ));

    $this->tenant = $created->tenant;
    $this->owner = $created->owner;
});

afterEach(fn () => app(CurrentTenant::class)->forget());

test('a new business starts with services and hours to do, and the owner ready to take bookings', function () {
    $this->actingAs($this->owner)
        ->get(tenantRoute($this->tenant, 'onboarding'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Onboarding')
            ->where('canManageServices', true)
            ->has('services', 0)
            ->has('hours', 0)
            ->has('staff', 1)
            ->where('staff.0.name', 'Olivia')
            ->where('bookingUrl', 'http://acme.scheduling-saas.test/book'),
        );
});

test('services and hours saved from the wizard complete its steps and open the booking page', function () {
    $staffId = app(CurrentTenant::class)->runAs($this->tenant, fn () => StaffProfile::forUser($this->owner)->id);
    $onboarding = tenantRoute($this->tenant, 'onboarding');

    $this->actingAs($this->owner)->from($onboarding)
        ->post(tenantRoute($this->tenant, 'services.store'), [
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'buffer_after_minutes' => 0,
            'price' => '50.00',
            'is_active' => true,
            'staff_profile_ids' => [$staffId],
        ])
        ->assertRedirect($onboarding);

    $this->actingAs($this->owner)->from($onboarding)
        ->put(tenantRoute($this->tenant, 'team.members.working-hours.update', ['member' => $this->owner->id]), [
            'hours' => [['weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '18:00']],
        ])
        ->assertRedirect($onboarding);

    $this->actingAs($this->owner)
        ->get($onboarding)
        ->assertInertia(fn (Assert $page) => $page
            ->has('services', 1)
            ->where('services.0.name', 'Haircut')
            ->has('hours', 1)
            ->where('hours.0.starts_at', '09:00'),
        );

    $this->get(tenantRoute($this->tenant, 'booking.show'))
        ->assertInertia(fn (Assert $page) => $page->has('services', 1));
});

test('staff see only their own hours step', function () {
    $staff = app(CurrentTenant::class)->runAs($this->tenant, fn () => User::factory()->staff()->create());

    $this->actingAs($staff)
        ->get(tenantRoute($this->tenant, 'onboarding'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManageServices', false)
            ->where('canInvite', false),
        );
});
