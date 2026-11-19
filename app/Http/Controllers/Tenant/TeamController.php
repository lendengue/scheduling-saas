<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Invitation::class);

        return Inertia::render('Tenant/Team/Index', [
            'members' => User::query()
                ->with('roles')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->tenantRole()?->label(),
                ]),
            'invitations' => Invitation::query()
                ->pending()
                ->latest()
                ->get()
                ->map(fn (Invitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->label(),
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ]),
            'roles' => Role::invitableOptions(),
        ]);
    }
}
