<?php

namespace App\Actions\Staff;

use App\Enums\Role;
use App\Models\StaffProfile;
use App\Services\Availability\AvailabilityCache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Updates a member's role and professional settings.
 */
class UpdateStaffMember
{
    public function __construct(private readonly AvailabilityCache $cache) {}

    /**
     * @param  array{bio: string|null, color: string, is_bookable: bool}  $attributes
     * @param  list<int>  $serviceIds
     */
    public function handle(StaffProfile $profile, ?Role $role, array $attributes, array $serviceIds): StaffProfile
    {
        return DB::transaction(function () use ($profile, $role, $attributes, $serviceIds): StaffProfile {
            $user = $profile->user;

            if ($role !== null && $role !== $user->tenantRole()) {
                if ($user->isOwner() || ! in_array($role, Role::invitable(), true)) {
                    throw new InvalidArgumentException('The owner role cannot be granted or removed here.');
                }

                $user->syncRoles([$role]);
            }

            $profile->update($attributes);
            $profile->services()->sync($serviceIds);
            $this->cache->flush($profile->tenant_id);

            return $profile;
        });
    }
}
