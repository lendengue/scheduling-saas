<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->get(tenantRoute($tenant, 'two-factor.login'));

    $response->assertRedirect(tenantRoute($tenant, 'login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(tenantRoute($user, 'login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(tenantRoute($user, 'two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/TwoFactorChallenge'),
        );
});
