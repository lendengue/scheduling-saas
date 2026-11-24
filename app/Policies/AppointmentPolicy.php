<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\User;

/**
 * Owners and admins manage every appointment of the tenant. Staff members
 * see and manage only the appointments in their own agenda.
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function viewAll(User $user): bool
    {
        return $user->hasTenantRole(Role::Owner, Role::Admin);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id
            && ($this->viewAll($user) || $appointment->staffProfile->user_id === $user->id);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }

    /**
     * Whether the user may place an appointment in this professional's agenda.
     */
    public function createFor(User $user, StaffProfile $profile): bool
    {
        return $user->tenant_id === $profile->tenant_id
            && ($this->viewAll($user) || $profile->user_id === $user->id);
    }
}
