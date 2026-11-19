<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

function registrationData(array $overrides = []): array
{
    return [
        'business_name' => 'Acme Barbershop',
        'slug' => 'acme',
        'timezone' => 'America/Sao_Paulo',
        'name' => 'Ada Owner',
        'email' => 'Ada@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

test('the registration page renders on the central domain', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Central/Register')
            ->has('timezones'),
        );
});

test('a business can register and lands on its onboarding, logged in', function () {
    $this->freezeTime();

    $response = $this->post(route('register.store'), registrationData());

    $tenant = Tenant::where('slug', 'acme')->sole();
    $owner = $tenant->users()->sole();

    expect($tenant->name)->toBe('Acme Barbershop')
        ->and($tenant->timezone)->toBe('America/Sao_Paulo')
        ->and($tenant->currency)->toBe('BRL')
        ->and($tenant->booking_settings)->toEqual(Tenant::defaultBookingSettings())
        ->and($tenant->trial_ends_at->toDateTimeString())->toBe(now()->addDays(14)->toDateTimeString())
        ->and($owner->email)->toBe('ada@example.com')
        ->and($owner->is_super_admin)->toBeFalse();

    app(CurrentTenant::class)->runAs($tenant, fn () => expect($owner->fresh()->tenantRole())->toBe(Role::Owner));

    // The central domain hands the owner off to the tenant subdomain.
    $handoffUrl = $response->headers->get('Location');
    expect($handoffUrl)->toStartWith('http://acme.scheduling-saas.test/auth/handoff/');
    $this->assertGuest();

    $this->get($handoffUrl)->assertRedirect('http://acme.scheduling-saas.test/app/onboarding');
    $this->assertAuthenticatedAs($owner);

    $this->get('http://acme.scheduling-saas.test/app/onboarding')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Tenant/Onboarding')->where('canInvite', true));
});

test('inertia requests receive an external redirect to the subdomain', function () {
    $this->withHeaders(['X-Inertia' => 'true'])
        ->post(route('register.store'), registrationData())
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');
});

test('the login handoff can only be used once', function () {
    $handoffUrl = $this->post(route('register.store'), registrationData())->headers->get('Location');

    $this->get($handoffUrl);
    auth()->logout();

    $this->get($handoffUrl)->assertRedirect('http://acme.scheduling-saas.test/login');
    $this->assertGuest();
});

test('the login handoff only works on the subdomain it was issued for', function () {
    Tenant::factory()->create(['slug' => 'other']);
    $handoffUrl = $this->post(route('register.store'), registrationData())->headers->get('Location');

    $this->get(str_replace('acme.', 'other.', $handoffUrl));

    $this->assertGuest();
});

test('the login handoff expires', function () {
    $handoffUrl = $this->post(route('register.store'), registrationData())->headers->get('Location');

    $this->travel(61)->seconds();

    $this->get($handoffUrl);
    $this->assertGuest();
});

test('invalid slugs are rejected', function (string $slug) {
    $this->post(route('register.store'), registrationData(['slug' => $slug]))
        ->assertSessionHasErrors('slug');

    expect(Tenant::count())->toBe(0);
})->with([
    'too short' => 'ab',
    'too long' => str_repeat('a', 31),
    'uppercase' => 'Acme',
    'underscore' => 'acme_studio',
    'space' => 'acme studio',
    'dot' => 'acme.studio',
    'accent' => 'acmé',
    'leading hyphen' => '-acme',
    'trailing hyphen' => 'acme-',
    'empty' => '',
]);

test('reserved slugs are rejected', function (string $slug) {
    $this->post(route('register.store'), registrationData(['slug' => $slug]))
        ->assertSessionHasErrors(['slug' => 'This address is reserved. Please choose another one.']);
})->with(['www', 'app', 'admin', 'api', 'mail']);

test('duplicate slugs are rejected, including those of deleted tenants', function (bool $deleted) {
    $existing = Tenant::factory()->create(['slug' => 'acme']);
    if ($deleted) {
        $existing->delete();
    }

    $this->post(route('register.store'), registrationData())
        ->assertSessionHasErrors(['slug' => 'This address is already taken.']);
})->with(['active tenant' => false, 'deleted tenant' => true]);

test('the timezone must be valid', function () {
    $this->post(route('register.store'), registrationData(['timezone' => 'Mars/Olympus']))
        ->assertSessionHasErrors('timezone');
});

test('registration is rate limited', function () {
    foreach (range(1, 10) as $attempt) {
        $this->post(route('register.store'), registrationData(['slug' => 'x']));
    }

    $this->post(route('register.store'), registrationData())->assertTooManyRequests();
});

test('registration is not available on tenant subdomains', function () {
    $tenant = Tenant::factory()->create();

    $this->post($tenant->url('/register'), registrationData())->assertNotFound();
    expect(Tenant::count())->toBe(1);
});

test('the same email can own businesses on different tenants', function () {
    $this->post(route('register.store'), registrationData());
    auth()->logout();
    $this->post(route('register.store'), registrationData(['slug' => 'acme-two']));

    expect(User::withoutGlobalScopes()->where('email', 'ada@example.com')->count())->toBe(2);
});
