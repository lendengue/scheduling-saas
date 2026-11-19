<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Seeder;

/**
 * Demo tenant "demo" (a fictional barbershop) with one user per role.
 * All demo users share the password "password".
 *
 * Services, working hours, customers and appointments are added in Phase 2.
 */
class DemoSeeder extends Seeder
{
    public function run(CurrentTenant $currentTenant): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Barbershop',
            'slug' => 'demo',
            'timezone' => 'America/Sao_Paulo',
        ]);

        $currentTenant->runAs($tenant, function () {
            foreach ([
                Role::Owner->value => ['Olivia Owner', 'owner@demo.test'],
                Role::Admin->value => ['Adam Admin', 'admin@demo.test'],
                Role::Staff->value => ['Sofia Staff', 'staff@demo.test'],
            ] as $role => [$name, $email]) {
                User::factory()->withRole(Role::from($role))->create([
                    'name' => $name,
                    'email' => $email,
                ]);
            }
        });
    }
}
