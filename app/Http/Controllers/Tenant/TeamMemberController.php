<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Staff\UpdateStaffMember;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateStaffMemberRequest;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\CurrentTenant;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A member's professional settings, weekly hours and time off.
 */
class TeamMemberController extends Controller
{
    public function show(User $member, #[CurrentUser] User $user, CurrentTenant $currentTenant): Response
    {
        $profile = StaffProfile::forUser($member)->load(['services:id', 'workingHours']);
        Gate::authorize('view', $profile);

        $timezone = $currentTenant->getOrFail()->timezone;

        return Inertia::render('Tenant/Team/Show', [
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->tenantRole()?->value,
                'is_owner' => $member->isOwner(),
                'is_self' => $member->is($user),
            ],
            'profile' => [
                'bio' => $profile->bio,
                'color' => $profile->color,
                'is_bookable' => $profile->is_bookable,
                'service_ids' => $profile->services->modelKeys(),
            ],
            'hours' => $profile->workingHours->map(fn (WorkingHour $hour) => [
                'weekday' => $hour->weekday,
                'starts_at' => substr($hour->starts_at, 0, 5),
                'ends_at' => substr($hour->ends_at, 0, 5),
            ]),
            'timeOffs' => $profile->timeOffs()->where('ends_at', '>', now())->get()->map(fn (TimeOff $timeOff) => [
                'id' => $timeOff->id,
                'starts_at' => $timeOff->starts_at->tz($timezone)->format('Y-m-d H:i'),
                'ends_at' => $timeOff->ends_at->tz($timezone)->format('Y-m-d H:i'),
                'reason' => $timeOff->reason,
            ]),
            'services' => Service::query()->ordered()->get(['id', 'name']),
            'roles' => Role::invitableOptions(),
            'timezone' => $timezone,
            'can' => [
                'update' => $user->can('update', $profile),
            ],
        ]);
    }

    public function update(UpdateStaffMemberRequest $request, User $member, UpdateStaffMember $update): RedirectResponse
    {
        $update->handle($request->profile(), $request->role(), $request->profileAttributes(), $request->serviceIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return back();
    }
}
