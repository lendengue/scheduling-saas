<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreTimeOffRequest;
use App\Models\StaffProfile;
use App\Models\TimeOff;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TimeOffController extends Controller
{
    public function store(StoreTimeOffRequest $request, User $member): RedirectResponse
    {
        $request->profile()->timeOffs()->create($request->timeOffAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time off added.')]);

        return back();
    }

    public function destroy(User $member, TimeOff $timeOff): RedirectResponse
    {
        $profile = StaffProfile::forUser($member);
        abort_unless($timeOff->staff_profile_id === $profile->id, 404);
        Gate::authorize('manageSchedule', $profile);

        $timeOff->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Time off removed.')]);

        return back();
    }
}
