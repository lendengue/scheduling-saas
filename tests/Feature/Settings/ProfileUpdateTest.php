<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(tenantRoute($user, 'profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(tenantRoute($user, 'profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($user, 'profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email is stored in lowercase so the user can still log in', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->patch(tenantRoute($user, 'profile.update'), [
            'name' => 'Test User',
            'email' => 'Test@Example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('test@example.com');

    $this->post(tenantRoute($user, 'logout'));

    $this->post(tenantRoute($user, 'login.store'), [
        'email' => 'Test@Example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('email must be unique within the tenant regardless of case', function () {
    $user = User::factory()->create();
    User::factory()->for($user->tenant)->create(['email' => 'taken@example.com']);

    $response = $this
        ->actingAs($user)
        ->from(tenantRoute($user, 'profile.edit'))
        ->patch(tenantRoute($user, 'profile.update'), [
            'name' => 'Test User',
            'email' => 'Taken@Example.com',
        ]);

    $response
        ->assertSessionHasErrors('email')
        ->assertRedirect(tenantRoute($user, 'profile.edit'));
});

test('the same email may be used in another tenant', function () {
    User::factory()->create(['email' => 'shared@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(tenantRoute($user, 'profile.update'), [
            'name' => 'Test User',
            'email' => 'shared@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('shared@example.com');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(tenantRoute($user, 'profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($user, 'profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(tenantRoute($user, 'profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantRoute($user, 'home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(tenantRoute($user, 'profile.edit'))
        ->delete(tenantRoute($user, 'profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(tenantRoute($user, 'profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('the owner cannot delete their account', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->delete(tenantRoute($owner, 'profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect($owner->fresh())->not->toBeNull();
});
