<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Team\InviteTeamMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, #[CurrentUser] User $user, InviteTeamMember $invite): RedirectResponse
    {
        $invitation = $invite->handle($user, $request->string('email')->toString(), $request->role());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }
}
