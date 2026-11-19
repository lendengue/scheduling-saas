<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Service;
use App\Models\User;

/**
 * The service catalog is managed by owners and admins.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasTenantRole(Role::Owner, Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasTenantRole(Role::Owner, Role::Admin);
    }

    public function update(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id
            && $user->hasTenantRole(Role::Owner, Role::Admin);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
