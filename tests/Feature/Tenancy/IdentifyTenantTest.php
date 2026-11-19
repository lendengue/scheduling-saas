<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/_test/context', fn (CurrentTenant $current) => ['tenant' => $current->id()]);
});

test('the central domain has no tenant', function () {
    $this->get('http://scheduling-saas.test/_test/context')->assertExactJson(['tenant' => null]);
});

test('a tenant subdomain sets the current tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);

    $this->get('http://acme.scheduling-saas.test/_test/context')->assertExactJson(['tenant' => $tenant->id]);
});

test('an unknown subdomain is not found', function () {
    $this->get('http://ghost.scheduling-saas.test/_test/context')->assertNotFound();
});

test('a soft-deleted tenant is not found', function () {
    Tenant::factory()->create(['slug' => 'gone'])->delete();

    $this->get('http://gone.scheduling-saas.test/_test/context')->assertNotFound();
});

test('unrelated hosts are not found', function (string $host) {
    $this->get("http://{$host}/_test/context")->assertNotFound();
})->with(['example.com', 'acme.example.com', 'a.b.scheduling-saas.test']);

test('central routes are not available on tenant subdomains', function () {
    $tenant = Tenant::factory()->create();

    $this->get($tenant->url('/register'))->assertNotFound();
});

test('tenant routes are not available on the central domain', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertNotFound();
});

test('shares the tenant with the frontend', function () {
    $tenant = Tenant::factory()->create(['name' => 'Acme Studio']);

    $this->get(tenantRoute($tenant, 'login'))
        ->assertInertia(fn ($page) => $page
            ->where('tenant.name', 'Acme Studio')
            ->where('tenant.slug', $tenant->slug)
            ->where('appDomain', 'scheduling-saas.test'),
        );
});
