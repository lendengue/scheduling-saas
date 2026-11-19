<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Staff\ReplaceWorkingHours;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\WorkingHoursRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WorkingHoursController extends Controller
{
    public function update(WorkingHoursRequest $request, User $member, ReplaceWorkingHours $replace): RedirectResponse
    {
        $replace->handle($request->profile(), $request->intervals());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Working hours saved.')]);

        return back();
    }
}
