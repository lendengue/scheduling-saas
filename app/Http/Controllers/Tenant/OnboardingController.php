<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First-run wizard shown after registration. Phase 1 ships the team step;
 * the services and working hours steps arrive with the scheduling core.
 */
class OnboardingController extends Controller
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        return Inertia::render('Tenant/Onboarding', [
            'canInvite' => $user->can('create', Invitation::class),
            'roles' => Role::invitableOptions(),
            'invitations' => Invitation::query()->pending()->latest()->get(['id', 'email', 'role'])
                ->map(fn (Invitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->label(),
                ]),
        ]);
    }
}
