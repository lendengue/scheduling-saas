<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the real PostgreSQL database (never SQLite),
| because the schema relies on PostgreSQL-only features such as
| exclusion constraints.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Absolute URL of a named route on a tenant subdomain.
 *
 * @param  array<string, mixed>  $parameters
 */
function tenantRoute(Tenant|User $tenant, string $name, array $parameters = []): string
{
    $tenant = $tenant instanceof User ? $tenant->tenant : $tenant;

    return $tenant->url(route($name, $parameters, absolute: false));
}
