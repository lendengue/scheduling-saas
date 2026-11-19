<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Team\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(Request $request, Invitation $invitation, CurrentTenant $currentTenant): Response
    {
        $token = $request->string('token')->toString();

        // A wrong token reveals nothing; a valid but used or expired one is "gone".
        abort_unless(SecureToken::matches($token, $invitation->token_hash), 404);
        abort_unless($invitation->isPending(), 410);

        return Inertia::render('Tenant/Invitations/Accept', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->label(),
            ],
            'tenantName' => $currentTenant->getOrFail()->name,
            'token' => $token,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, Invitation $invitation, AcceptInvitation $accept): RedirectResponse
    {
        $user = $accept->handle(
            $invitation,
            $request->string('token')->toString(),
            $request->string('name')->trim()->toString(),
            $request->string('password')->toString(),
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to the team!')]);

        return redirect()->route('dashboard');
    }
}
