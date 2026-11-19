<?php

namespace App\Models\Scopes;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts queries to the current tenant.
 *
 * Fails closed: without a current tenant the query only matches rows whose
 * tenant_id is NULL. Tenant-owned tables never have such rows, so nothing
 * leaks; for users it matches platform super admins only. Code that must
 * cross tenants (super admin panel, scheduler) removes the scope explicitly.
 *
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();
        $column = $model->qualifyColumn('tenant_id');

        $tenantId === null
            ? $builder->whereNull($column)
            : $builder->where($column, $tenantId);
    }
}
