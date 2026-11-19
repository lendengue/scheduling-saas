<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;

/**
 * Team management (members and invitations) is limited to owners and admins.
 */
class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasTenantRole(Role::Owner, Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasTenantRole(Role::Owner, Role::Admin);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->tenant_id === $invitation->tenant_id
            && $user->hasTenantRole(Role::Owner, Role::Admin);
    }
}
