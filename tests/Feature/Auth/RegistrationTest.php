<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a user can register with email and password', function () {
    Notification::fake();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'pest-test-device',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'name', 'email', 'roles'], 'token']);

    $user = User::where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('listener'))->toBeTrue();
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('registration requires a unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Someone Else',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'pest-test-device',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('registration requires matching password confirmation', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
        'password_confirmation' => 'not-matching',
        'device_name' => 'pest-test-device',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('password');
});
