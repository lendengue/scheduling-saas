<?php

use App\Models\Tenant;
use App\Models\User;

test('guests are redirected to the tenant login page', function () {
    $tenant = Tenant::factory()->create();

    $this->get(tenantRoute($tenant, 'dashboard'))
        ->assertRedirect(tenantRoute($tenant, 'login'));
});

test('members can visit the dashboard', function () {
    $user = User::factory()->owner()->create();

    $this->actingAs($user)
        ->get(tenantRoute($user, 'dashboard'))
        ->assertOk();
});

test('the tenant root opens the public booking page', function () {
    $tenant = Tenant::factory()->create();

    $this->get($tenant->url('/'))->assertRedirect(tenantRoute($tenant, 'booking.show'));
});
