<?php

use App\Actions\Team\InviteTeamMember;
use App\Enums\Role;
use App\Exceptions\TenantMismatchException;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\Fixtures\RecordTenantContextJob;

/*
| A user of tenant A must never read or change data of tenant B: not through
| Eloquent, not through a URL or a tampered id, and not through a queued job.
*/

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['slug' => 'alpha']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'bravo']);

    $this->ownerA = User::factory()->for($this->tenantA)->owner()->create(['email' => 'owner@alpha.test']);
    $this->ownerB = User::factory()->for($this->tenantB)->owner()->create(['email' => 'owner@bravo.test']);

    $this->invitationB = Invitation::factory()->for($this->tenantB)->create(['email' => 'invitee@bravo.test']);

    $this->current = app(CurrentTenant::class);
});

afterEach(function () {
    $this->current->forget();
});

describe('Eloquent', function () {
    test('queries only see rows of the current tenant', function () {
        $this->current->set($this->tenantA);

        expect(User::pluck('email')->all())->toBe(['owner@alpha.test'])
            ->and(User::find($this->ownerB->id))->toBeNull()
            ->and(Invitation::find($this->invitationB->id))->toBeNull()
            ->and(Invitation::count())->toBe(0);
    });

    test('without a tenant, tenant-owned rows are hidden (fail closed)', function () {
        expect(Invitation::count())->toBe(0)
            ->and(User::count())->toBe(0);
    });

    test('without a tenant, only platform super admins are visible', function () {
        $admin = User::factory()->superAdmin()->create();

        expect(User::pluck('id')->all())->toBe([$admin->id]);
    });

    test('new records are assigned to the current tenant', function () {
        $this->current->set($this->tenantA);

        $user = User::create(['name' => 'New', 'email' => 'new@alpha.test', 'password' => 'secret-password']);

        expect($user->tenant_id)->toBe($this->tenantA->id);
    });

    test('records cannot be created for another tenant', function () {
        $this->current->set($this->tenantA);

        User::factory()->for($this->tenantB)->create();
    })->throws(TenantMismatchException::class);

    test('records cannot be moved to another tenant', function () {
        $this->current->set($this->tenantA);

        $this->ownerA->forceFill(['tenant_id' => $this->tenantB->id])->save();
    })->throws(TenantMismatchException::class);

    test('roles are scoped to the current tenant', function () {
        $this->current->set($this->tenantB);

        expect(User::find($this->ownerA->id))->toBeNull();

        $this->current->set($this->tenantA);
        $owner = User::findOrFail($this->ownerA->id);

        expect($owner->tenantRole())->toBe(Role::Owner);

        // Granting a role while tenant A is active never leaks to tenant B.
        $owner->assignRole(Role::Admin);
        $this->current->set($this->tenantB);
        $owner->unsetRelation('roles');

        expect($owner->roles)->toBeEmpty();
    });
});

describe('HTTP', function () {
    test("a member cannot open another tenant's panel", function () {
        $this->actingAs($this->ownerA)
            ->get(tenantRoute($this->tenantB, 'dashboard'))
            ->assertForbidden();
    });

    test('a session from one tenant does not authenticate on another', function () {
        $this->post(tenantRoute($this->tenantA, 'login.store'), [
            'email' => 'owner@alpha.test',
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($this->ownerA);

        // Same session, other host: the session user id resolves through the
        // tenant scope and is not found in tenant B.
        app('auth')->forgetGuards();
        $this->get(tenantRoute($this->tenantB, 'dashboard'))
            ->assertRedirect(tenantRoute($this->tenantB, 'login'));
    });

    test('credentials of tenant A do not log in on tenant B', function () {
        $this->post(tenantRoute($this->tenantB, 'login.store'), [
            'email' => 'owner@alpha.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    test('the same email logs into the right tenant', function () {
        $alsoInB = User::factory()->for($this->tenantB)->create(['email' => 'owner@alpha.test']);

        $this->post(tenantRoute($this->tenantB, 'login.store'), [
            'email' => 'owner@alpha.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($alsoInB);
    });

    test("an id of another tenant's record is not found", function () {
        $this->actingAs($this->ownerA)
            ->delete(tenantRoute($this->tenantA, 'team.invitations.destroy', ['invitation' => $this->invitationB->id]))
            ->assertNotFound();

        expect(Invitation::withoutGlobalScopes()->find($this->invitationB->id))->not->toBeNull();
    });

    test("an invitation link cannot be opened on another tenant's subdomain", function () {
        $token = InvitationFactory::$lastPlainToken;

        $this->get(tenantRoute($this->tenantA, 'invitations.show', [
            'invitation' => $this->invitationB->id,
            'token' => $token,
        ]))->assertNotFound();
    });

    test("the team page lists only the tenant's own members and invitations", function () {
        $this->actingAs($this->ownerA)
            ->get(tenantRoute($this->tenantA, 'team.index'))
            ->assertInertia(fn ($page) => $page
                ->has('members', 1)
                ->where('members.0.email', 'owner@alpha.test')
                ->has('invitations', 0),
            );
    });
});

describe('Jobs', function () {
    beforeEach(function () {
        config(['queue.default' => 'database']);
    });

    test('a queued job runs in the tenant it was dispatched from', function () {
        $this->current->runAs($this->tenantA, function () {
            RecordTenantContextJob::dispatch();
        });

        // The worker starts with another tenant active, as a stale context would.
        $this->current->set($this->tenantB);
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

        expect(Cache::get('job.tenant_id'))->toBe($this->tenantA->id)
            ->and(Cache::get('job.visible_emails'))->toBe(['owner@alpha.test'])
            ->and($this->current->check())->toBeFalse();
    });

    test('a job dispatched without a tenant sees no tenant data', function () {
        RecordTenantContextJob::dispatch();

        $this->current->set($this->tenantA);
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

        expect(Cache::get('job.tenant_id'))->toBeNull()
            ->and(Cache::get('job.visible_emails'))->toBe([]);
    });

    test('a job of a deleted tenant sees no tenant data', function () {
        $this->current->runAs($this->tenantA, function () {
            RecordTenantContextJob::dispatch();
        });
        $this->tenantA->delete();

        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

        expect(Cache::get('job.tenant_id'))->toBeNull()
            ->and(Cache::get('job.visible_emails'))->toBe([]);
    });

    test('queued mail is rendered in the tenant it was queued from', function () {
        // The invitation mail lazily loads its inviter, a tenant-scoped User,
        // inside the worker: this only works if the tenant context travels.
        $this->current->runAs($this->tenantA, function () {
            app(InviteTeamMember::class)->handle($this->ownerA, 'new@alpha.test', Role::Staff);
        });

        $this->current->set($this->tenantB);
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

        $messages = app('mailer')->getSymfonyTransport()->messages();

        expect($messages)->toHaveCount(1)
            ->and($messages[0]->getOriginalMessage()->getTextBody())
            ->toContain("{$this->ownerA->name} invited you to join")
            ->toContain('http://alpha.scheduling-saas.test/invitations/');
    });
});
