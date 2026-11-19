<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\StaffProfile;
use App\Models\User;

/**
 * Owners and admins manage every professional. Staff members see and manage
 * their own working hours and time off, but not their role or services.
 */
class StaffProfilePolicy
{
    public function view(User $user, StaffProfile $profile): bool
    {
        return $this->manageSchedule($user, $profile);
    }

    public function update(User $user, StaffProfile $profile): bool
    {
        return $user->tenant_id === $profile->tenant_id
            && $user->hasTenantRole(Role::Owner, Role::Admin);
    }

    public function manageSchedule(User $user, StaffProfile $profile): bool
    {
        return $user->tenant_id === $profile->tenant_id
            && ($profile->user_id === $user->id || $user->hasTenantRole(Role::Owner, Role::Admin));
    }
}
