<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Roles are global (tenant_id = NULL) and assigned per tenant through the
     * tenant_id column of model_has_roles.
     */
    public function up(): void
    {
        $now = now();

        DB::table(config('permission.table_names.roles'))->insert(array_map(fn (Role $role) => [
            'name' => $role->value,
            'guard_name' => 'web',
            'tenant_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], Role::cases()));

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.roles'))
            ->whereNull('tenant_id')
            ->whereIn('name', array_column(Role::cases(), 'value'))
            ->delete();
    }
};
