<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = User::factory()->for($this->tenant)->owner()->create();
});

function serviceInTenant(Tenant $tenant, array $attributes = []): Service
{
    return app(CurrentTenant::class)->runAs($tenant, fn () => Service::factory()->create($attributes));
}

$validService = [
    'name' => 'Haircut',
    'description' => 'Classic cut',
    'duration_minutes' => 30,
    'buffer_after_minutes' => 10,
    'price' => '45,90',
    'is_active' => true,
];

test('owners and admins manage the catalog', function (string $role) use ($validService) {
    $user = $role === 'owner' ? $this->owner : User::factory()->for($this->tenant)->admin()->create();

    $this->actingAs($user)
        ->post(tenantRoute($this->tenant, 'services.store'), $validService)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $service = Service::withoutGlobalScopes()->sole();

    expect($service->tenant_id)->toBe($this->tenant->id)
        ->and($service->price_cents)->toBe(4590)
        ->and($service->blockedMinutes())->toBe(40)
        ->and($service->position)->toBe(1);

    $this->actingAs($user)
        ->put(tenantRoute($this->tenant, 'services.update', ['service' => $service->id]), [...$validService, 'name' => 'Long cut', 'price' => '50'])
        ->assertSessionHasNoErrors();

    expect($service->fresh()->name)->toBe('Long cut')
        ->and($service->fresh()->price_cents)->toBe(5000);

    $this->actingAs($user)
        ->delete(tenantRoute($this->tenant, 'services.destroy', ['service' => $service->id]))
        ->assertRedirect();

    expect(Service::withoutGlobalScopes()->withTrashed()->sole()->trashed())->toBeTrue();
})->with(['owner', 'admin']);

test('staff cannot manage the catalog', function () use ($validService) {
    $staff = User::factory()->for($this->tenant)->staff()->create();
    $service = serviceInTenant($this->tenant);

    $this->actingAs($staff)->get(tenantRoute($this->tenant, 'services.index'))->assertForbidden();
    $this->actingAs($staff)->post(tenantRoute($this->tenant, 'services.store'), $validService)->assertForbidden();
    $this->actingAs($staff)->put(tenantRoute($this->tenant, 'services.update', ['service' => $service->id]), $validService)->assertForbidden();
    $this->actingAs($staff)->delete(tenantRoute($this->tenant, 'services.destroy', ['service' => $service->id]))->assertForbidden();
});

test('durations, buffers and prices are validated', function (array $override, string $field) use ($validService) {
    $this->actingAs($this->owner)
        ->post(tenantRoute($this->tenant, 'services.store'), [...$validService, ...$override])
        ->assertSessionHasErrors($field);
})->with([
    'duration not a multiple of 5' => [['duration_minutes' => 32], 'duration_minutes'],
    'duration too short' => [['duration_minutes' => 0], 'duration_minutes'],
    'duration too long' => [['duration_minutes' => 485], 'duration_minutes'],
    'negative buffer' => [['buffer_after_minutes' => -5], 'buffer_after_minutes'],
    'buffer too long' => [['buffer_after_minutes' => 125], 'buffer_after_minutes'],
    'three decimals' => [['price' => '10.999'], 'price'],
    'negative price' => [['price' => '-1'], 'price'],
]);

test('the catalog lists services in order with formatted prices', function () {
    serviceInTenant($this->tenant, ['name' => 'Second', 'position' => 2, 'price_cents' => 12990]);
    serviceInTenant($this->tenant, ['name' => 'First', 'position' => 1]);
    serviceInTenant(Tenant::factory()->create(), ['name' => 'Elsewhere']);

    $this->actingAs($this->owner)
        ->get(tenantRoute($this->tenant, 'services.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/Services/Index')
            ->has('services', 2)
            ->where('services.0.name', 'First')
            ->where('services.1.price', '129.90')
            ->where('services.1.price_formatted', 'R$129.90'),
        );
});

test('services can be reordered', function () {
    $a = serviceInTenant($this->tenant, ['position' => 1]);
    $b = serviceInTenant($this->tenant, ['position' => 2]);
    $c = serviceInTenant($this->tenant, ['position' => 3]);

    $this->actingAs($this->owner)
        ->put(tenantRoute($this->tenant, 'services.reorder'), ['ids' => [$c->id, $a->id, $b->id]])
        ->assertSessionHasNoErrors();

    expect([$c->fresh()->position, $a->fresh()->position, $b->fresh()->position])->toBe([1, 2, 3]);
});

test("another tenant's services cannot be reordered or edited", function () use ($validService) {
    $foreign = serviceInTenant(Tenant::factory()->create(), ['position' => 7]);

    $this->actingAs($this->owner)
        ->put(tenantRoute($this->tenant, 'services.reorder'), ['ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');

    $this->actingAs($this->owner)
        ->put(tenantRoute($this->tenant, 'services.update', ['service' => $foreign->id]), $validService)
        ->assertNotFound();

    expect($foreign->fresh()->position)->toBe(7);
});
