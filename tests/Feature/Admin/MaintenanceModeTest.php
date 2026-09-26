<?php

use App\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('platform status is off by default', function () {
    $response = $this->getJson('/api/platform/status');

    $response->assertOk();
    expect($response->json('maintenance_mode'))->toBeFalse();
});

test('a super-admin can enable maintenance mode with a message', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $response = $this->actingAs($superAdmin, 'sanctum')->putJson('/api/admin/platform-settings/maintenance', [
        'enabled' => true,
        'message' => 'Upgrading the database, back in an hour.',
    ]);

    $response->assertOk();
    expect(PlatformSetting::get('maintenance_mode'))->toBeTrue();
    expect(PlatformSetting::get('maintenance_message'))->toBe('Upgrading the database, back in an hour.');
});

test('a moderator cannot toggle maintenance mode', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $this->actingAs($moderator, 'sanctum')->putJson('/api/admin/platform-settings/maintenance', [
        'enabled' => true,
    ])->assertForbidden();
});

test('during maintenance, an ordinary API call is blocked for a guest', function () {
    PlatformSetting::set('maintenance_mode', true);
    PlatformSetting::set('maintenance_message', 'Down for maintenance.');

    $response = $this->getJson('/api/discovery/trending');

    $response->assertStatus(503);
    expect($response->json('maintenance_message'))->toBe('Down for maintenance.');
});

test('during maintenance, an ordinary API call is blocked for a logged-in listener', function () {
    PlatformSetting::set('maintenance_mode', true);
    $listener = User::factory()->create();
    $listener->assignRole('listener');

    $this->actingAs($listener, 'sanctum')->getJson('/api/discovery/trending')->assertStatus(503);
});

test('during maintenance, a moderator can still use the API', function () {
    PlatformSetting::set('maintenance_mode', true);
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $this->actingAs($moderator, 'sanctum')->getJson('/api/discovery/trending')->assertOk();
});

test('during maintenance, login and platform status stay reachable for everyone', function () {
    PlatformSetting::set('maintenance_mode', true);
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('listener');

    $this->getJson('/api/platform/status')->assertOk();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'test',
    ])->assertOk();
});

test('a super-admin can turn maintenance mode back off', function () {
    PlatformSetting::set('maintenance_mode', true);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin, 'sanctum')->putJson('/api/admin/platform-settings/maintenance', [
        'enabled' => false,
    ])->assertOk();

    expect(PlatformSetting::get('maintenance_mode'))->toBeFalse();
});
