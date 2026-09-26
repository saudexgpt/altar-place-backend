<?php

use App\Models\User;

test('a user can change their password with the correct current password', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/auth/change-password', [
        'current_password' => 'old-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertOk();
    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('changing the password fails with the wrong current password', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/auth/change-password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('current_password');
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

test('changing the password requires confirmation to match', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/auth/change-password', [
        'current_password' => 'old-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'does-not-match',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('password');
});

test('a guest cannot change a password', function () {
    $this->putJson('/api/auth/change-password', [
        'current_password' => 'x',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnauthorized();
});
