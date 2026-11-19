<?php

use App\Enums\Role;
use App\Mail\TeamInvitationMail;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\SecureToken;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Mail::fake();

    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = User::factory()->for($this->tenant)->owner()->create();
});

/**
 * Signed acceptance link, as built by InviteTeamMember.
 */
function acceptLink(Invitation $invitation, string $token): string
{
    return $invitation->tenant->url(URL::temporarySignedRoute(
        'invitations.show',
        $invitation->expires_at,
        ['invitation' => $invitation->id, 'token' => $token],
        absolute: false,
    ));
}

/**
 * Create a pending invitation with a known token.
 *
 * @return array{Invitation, string}
 */
function pendingInvitation(Tenant $tenant, array $attributes = [], ?Closure $state = null): array
{
    $token = SecureToken::generate()->plain;
    $factory = Invitation::factory()->for($tenant)->withToken($token);
    $factory = $state ? $state($factory) : $factory;

    return [$factory->create(['email' => 'new@example.com', 'role' => Role::Staff, ...$attributes]), $token];
}

describe('inviting', function () {
    test('owners and admins can invite members', function (string $role) {
        $inviter = $role === 'owner' ? $this->owner : User::factory()->for($this->tenant)->admin()->create();

        $this->actingAs($inviter)
            ->post(tenantRoute($this->tenant, 'team.invitations.store'), ['email' => 'New@Example.com', 'role' => 'admin'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $invitation = Invitation::withoutGlobalScopes()->sole();

        expect($invitation->tenant_id)->toBe($this->tenant->id)
            ->and($invitation->email)->toBe('new@example.com')
            ->and($invitation->role)->toBe(Role::Admin)
            ->and($invitation->invited_by)->toBe($inviter->id)
            ->and($invitation->token_hash)->toHaveLength(64)
            ->and($invitation->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();

        Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($invitation) {
            parse_str((string) parse_url($mail->acceptUrl, PHP_URL_QUERY), $query);

            return $mail->hasTo('new@example.com')
                && str_starts_with($mail->acceptUrl, "http://acme.scheduling-saas.test/invitations/{$invitation->id}?")
                && isset($query['signature'], $query['expires'])
                && SecureToken::matches($query['token'], $invitation->token_hash);
        });
    })->with(['owner', 'admin']);

    test('staff cannot invite members', function () {
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($staff)
            ->post(tenantRoute($this->tenant, 'team.invitations.store'), ['email' => 'new@example.com', 'role' => 'staff'])
            ->assertForbidden();

        $this->actingAs($staff)->get(tenantRoute($this->tenant, 'team.index'))->assertForbidden();

        Mail::assertNothingQueued();
    });

    test('nobody can be invited as owner', function () {
        $this->actingAs($this->owner)
            ->post(tenantRoute($this->tenant, 'team.invitations.store'), ['email' => 'new@example.com', 'role' => 'owner'])
            ->assertSessionHasErrors('role');
    });

    test('existing members cannot be invited again', function () {
        User::factory()->for($this->tenant)->create(['email' => 'member@example.com']);

        $this->actingAs($this->owner)
            ->post(tenantRoute($this->tenant, 'team.invitations.store'), ['email' => 'MEMBER@example.com', 'role' => 'staff'])
            ->assertSessionHasErrors(['email' => 'This person is already a member of your team.']);
    });

    test('members of other tenants can be invited', function () {
        User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($this->owner)
            ->post(tenantRoute($this->tenant, 'team.invitations.store'), ['email' => 'member@example.com', 'role' => 'staff'])
            ->assertSessionHasNoErrors();
    });

    test('inviting the same email again replaces the pending invitation', function () {
        foreach (['staff', 'admin'] as $role) {
            $this->actingAs($this->owner)
                ->post(tenantRoute($this->tenant, 'team.invitations.store'), ['email' => 'new@example.com', 'role' => $role]);
        }

        expect(Invitation::withoutGlobalScopes()->sole()->role)->toBe(Role::Admin);
        Mail::assertQueued(TeamInvitationMail::class, 2);
    });

    test('pending invitations can be revoked', function () {
        [$invitation] = pendingInvitation($this->tenant);

        $this->actingAs($this->owner)
            ->delete(tenantRoute($this->tenant, 'team.invitations.destroy', ['invitation' => $invitation->id]))
            ->assertRedirect();

        expect(Invitation::withoutGlobalScopes()->count())->toBe(0);
    });

    test('staff cannot revoke invitations', function () {
        [$invitation] = pendingInvitation($this->tenant);
        $staff = User::factory()->for($this->tenant)->staff()->create();

        $this->actingAs($staff)
            ->delete(tenantRoute($this->tenant, 'team.invitations.destroy', ['invitation' => $invitation->id]))
            ->assertForbidden();
    });

    test('the team page lists members and pending invitations', function () {
        User::factory()->for($this->tenant)->staff()->create(['name' => 'Zoe Staff']);
        pendingInvitation($this->tenant);
        pendingInvitation($this->tenant, ['email' => 'old@example.com'], fn ($f) => $f->expired());

        $this->actingAs($this->owner)
            ->get(tenantRoute($this->tenant, 'team.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Team/Index')
                ->has('members', 2)
                ->where('members.1.role', 'Staff')
                ->has('invitations', 1)
                ->where('invitations.0.email', 'new@example.com')
                ->has('roles', 2),
            );
    });
});

describe('accepting', function () {
    test('the signed link shows the acceptance form', function () {
        [$invitation, $token] = pendingInvitation($this->tenant);

        $this->get(acceptLink($invitation, $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Invitations/Accept')
                ->where('invitation.email', 'new@example.com')
                ->where('token', $token),
            );
    });

    test('the invitee sets a password and joins the team', function () {
        [$invitation, $token] = pendingInvitation($this->tenant, ['role' => Role::Admin]);

        $this->post(tenantRoute($this->tenant, 'invitations.accept', ['invitation' => $invitation->id]), [
            'token' => $token,
            'name' => 'New Member',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(tenantRoute($this->tenant, 'dashboard'));

        $user = $this->tenant->users()->where('email', 'new@example.com')->sole();

        $this->assertAuthenticatedAs($user);
        expect($user->name)->toBe('New Member')
            ->and($invitation->fresh()->accepted_at)->not->toBeNull();
        app(CurrentTenant::class)->runAs($this->tenant, fn () => expect($user->fresh()->tenantRole())->toBe(Role::Admin));
    });

    test('a tampered signature is rejected', function () {
        [$invitation, $token] = pendingInvitation($this->tenant);

        $this->get(acceptLink($invitation, $token).'x')->assertForbidden();
    });

    test('a wrong token is not found', function () {
        [$invitation] = pendingInvitation($this->tenant);
        $wrong = SecureToken::generate()->plain;

        $this->get(acceptLink($invitation, $wrong))->assertNotFound();

        $this->post(tenantRoute($this->tenant, 'invitations.accept', ['invitation' => $invitation->id]), [
            'token' => $wrong,
            'name' => 'Intruder',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('token');

        $this->assertGuest();
    });

    test('expired and accepted invitations cannot be used', function (string $state, int $status) {
        [$invitation, $token] = pendingInvitation($this->tenant, [], fn ($f) => $f->{$state}());

        // The signature expires with the invitation; an accepted one is gone.
        $this->get(acceptLink($invitation, $token))->assertStatus($status);

        $this->post(tenantRoute($this->tenant, 'invitations.accept', ['invitation' => $invitation->id]), [
            'token' => $token,
            'name' => 'Late',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('token');
    })->with(['expired' => ['expired', 403], 'accepted' => ['accepted', 410]]);

    test('an invitation cannot be accepted twice', function () {
        [$invitation, $token] = pendingInvitation($this->tenant);
        $payload = ['token' => $token, 'name' => 'New', 'password' => 'password', 'password_confirmation' => 'password'];

        $this->post(tenantRoute($this->tenant, 'invitations.accept', ['invitation' => $invitation->id]), $payload);
        auth()->logout();
        $this->post(tenantRoute($this->tenant, 'invitations.accept', ['invitation' => $invitation->id]), $payload)
            ->assertSessionHasErrors('token');

        expect($this->tenant->users()->where('email', 'new@example.com')->count())->toBe(1);
    });
});
