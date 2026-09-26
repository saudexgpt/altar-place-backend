<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('all six platform roles are seeded', function () {
    foreach (RolesAndPermissionsSeeder::ROLES as $role) {
        expect(\Spatie\Permission\Models\Role::where('name', $role)->exists())->toBeTrue();
    }
});

test('a super-admin has every permission', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    expect($admin->can('moderate-content'))->toBeTrue();
    expect($admin->can('upload-content'))->toBeTrue();
});

test('a listener cannot access moderator-only permissions', function () {
    $listener = User::factory()->create();
    $listener->assignRole('listener');

    expect($listener->can('moderate-content'))->toBeFalse();
    expect($listener->can('stream-content'))->toBeTrue();
});
