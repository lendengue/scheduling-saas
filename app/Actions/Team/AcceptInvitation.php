<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\SecureToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a pending invitation into a user of the current tenant.
 */
class AcceptInvitation
{
    /**
     * Whether the plain token opens this invitation and it can still be used.
     */
    public function isUsable(Invitation $invitation, string $plainToken): bool
    {
        return SecureToken::matches($plainToken, $invitation->token_hash) && $invitation->isPending();
    }

    public function handle(Invitation $invitation, string $plainToken, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $plainToken, $name, $password): User {
            // Lock the row so the same invitation cannot be accepted twice concurrently.
            $invitation = Invitation::query()->lockForUpdate()->whereKey($invitation->getKey())->firstOrFail();

            if (! $this->isUsable($invitation, $plainToken)) {
                throw ValidationException::withMessages(['token' => 'This invitation is no longer valid.']);
            }

            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'This email already belongs to a member of this workspace.']);
            }

            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($invitation->role);

            // Staff members serve customers; admins opt in on their profile.
            StaffProfile::forUser($user, bookable: $invitation->role === Role::Staff);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
