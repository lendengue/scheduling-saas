<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(tenantRoute($user, 'password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/ConfirmPassword'),
    );
});

test('password confirmation requires authentication', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->get(tenantRoute($tenant, 'password.confirm'));

    $response->assertRedirect(tenantRoute($tenant, 'login'));
});
