<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('a password reset link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

    $response->assertOk();
    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('a password can be reset with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertOk();
    expect($user->fresh()->password)->not->toBeNull();

    $login = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'new-password123',
        'device_name' => 'pest-test-device',
    ]);
    $login->assertOk();
});

test('a password reset fails with an invalid token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});
