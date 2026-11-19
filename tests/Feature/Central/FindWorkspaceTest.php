<?php

use App\Models\Tenant;

test('forwards to the login page of the workspace', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->post(route('workspace.find'), ['slug' => ' ACME '])
        ->assertRedirect('http://acme.scheduling-saas.test/login');
});

test('unknown workspaces are reported', function () {
    $this->post(route('workspace.find'), ['slug' => 'ghost'])
        ->assertSessionHasErrors(['slug' => 'We could not find a workspace with this address.']);
});

test('deleted workspaces cannot be found', function () {
    Tenant::factory()->create(['slug' => 'gone'])->delete();

    $this->post(route('workspace.find'), ['slug' => 'gone'])->assertSessionHasErrors('slug');
});
