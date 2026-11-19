<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Mail\TeamInvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Support\SecureToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Invites someone to the current tenant. Any pending invitation for the same
 * email is replaced, so only the latest link works.
 */
class InviteTeamMember
{
    public function handle(User $inviter, string $email, Role $role): Invitation
    {
        if (! in_array($role, Role::invitable(), true)) {
            throw new InvalidArgumentException("The [{$role->value}] role cannot be granted by invitation.");
        }

        $token = SecureToken::generate();
        $expiresAt = now()->addDays((int) config('tenancy.invitation_expires_days'));

        $invitation = DB::transaction(function () use ($inviter, $email, $role, $token, $expiresAt): Invitation {
            Invitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

            $invitation = new Invitation;
            $invitation->forceFill([
                'email' => $email,
                'role' => $role,
                'token_hash' => $token->hash,
                'invited_by' => $inviter->getKey(),
                'expires_at' => $expiresAt,
            ])->save();

            return $invitation;
        });

        $invitation->setRelation('inviter', $inviter);

        Mail::to($email)->queue(new TeamInvitationMail($invitation, $this->acceptUrl($invitation, $token->plain)));

        return $invitation;
    }

    /**
     * Signed link on the tenant subdomain. The signature covers the path and
     * the query (including the token), and expires with the invitation.
     */
    private function acceptUrl(Invitation $invitation, string $plainToken): string
    {
        $path = URL::temporarySignedRoute(
            'invitations.show',
            $invitation->expires_at,
            ['invitation' => $invitation->getKey(), 'token' => $plainToken],
            absolute: false,
        );

        return $invitation->tenant->url($path);
    }
}
