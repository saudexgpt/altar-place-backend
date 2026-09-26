<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a user can login with correct credentials', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    $user->assignRole('listener');

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
        'device_name' => 'pest-test-device',
    ]);

    $response->assertOk()->assertJsonStructure(['user', 'token']);
});

test('login fails with incorrect password', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'pest-test-device',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('a suspended user cannot login', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'status' => 'suspended',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
        'device_name' => 'pest-test-device',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('login is rate limited after too many attempts', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'pest-test-device',
        ]);
    }

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
        'device_name' => 'pest-test-device',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('an authenticated user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pest-test-device')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/logout');

    $response->assertOk();
    expect($user->tokens()->count())->toBe(0);
});
