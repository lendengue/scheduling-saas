<?php

namespace App\Support\Tenancy;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * spatie/laravel-permission "team" = scheduling-saas tenant. The team is always the
 * current tenant, so roles can never be read or granted across tenants by
 * forgetting to call setPermissionsTeamId().
 */
class TenantTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int|string|null
    {
        return app(CurrentTenant::class)->id();
    }

    /**
     * Ignored on purpose: switch tenants with CurrentTenant::set() or
     * CurrentTenant::runAs() instead.
     */
    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        //
    }
}
