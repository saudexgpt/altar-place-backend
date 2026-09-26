<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('logging in establishes a session for stateful web clients', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'web',
    ]);

    $response->assertOk();
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
});

test('registering establishes a session for stateful web clients', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Web User',
        'email' => 'web-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'web',
    ]);

    $response->assertCreated();
    expect(auth()->check())->toBeTrue();
});

test('logout invalidates the session for a session-authenticated request', function () {
    $user = User::factory()->create();

    // A Referer matching a SANCTUM_STATEFUL_DOMAINS entry is what makes
    // EnsureFrontendRequestsAreStateful actually start a real session for
    // this request (as it would for a genuine browser request) — without
    // it, $request->session() isn't available to invalidate. This is really
    // guarding against the original bug: calling ->delete() on Sanctum's
    // TransientToken (session-authenticated requests never have a real,
    // deletable access token) used to throw instead of returning 200.
    $response = $this->withHeader('Referer', 'http://127.0.0.1')
        ->actingAs($user)
        ->postJson('/api/auth/logout');

    $response->assertOk();
});

test('logout-other-devices deletes all tokens when the request is session-authenticated', function () {
    $user = User::factory()->create();
    $user->createToken('mobile-device-1');
    $user->createToken('mobile-device-2');

    $this->withHeader('Referer', 'http://127.0.0.1')
        ->actingAs($user)
        ->postJson('/api/auth/logout-other-devices')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
