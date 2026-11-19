<?php

namespace App\Http\Requests\Staff;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Team routes are keyed by member ({member} = User of the current tenant).
 *
 * @mixin FormRequest
 */
trait ResolvesStaffProfile
{
    private ?StaffProfile $resolvedProfile = null;

    public function profile(): StaffProfile
    {
        $member = $this->route('member');
        abort_unless($member instanceof User, 404);

        return $this->resolvedProfile ??= StaffProfile::forUser($member);
    }
}
