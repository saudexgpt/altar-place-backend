<?php

use App\Models\Artist;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a user can apply to become a creator', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/creator/apply', [
        'artist_name' => 'Grace Notes',
        'bio' => 'Independent worship artist.',
    ]);

    $response->assertCreated();
    expect($user->fresh()->hasRole('creator'))->toBeTrue();
    expect(Artist::where('user_id', $user->id)->where('name', 'Grace Notes')->exists())->toBeTrue();
});

test('a user cannot apply twice', function () {
    $user = User::factory()->create();
    Artist::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/creator/apply', [
        'artist_name' => 'Another Name',
    ]);

    $response->assertStatus(422);
});

test('creator-only routes reject listeners', function () {
    $user = User::factory()->create();
    $user->assignRole('listener');

    $this->actingAs($user, 'sanctum')->getJson('/api/creator/dashboard')->assertForbidden();
});

test('creator-only routes reject guests', function () {
    $this->getJson('/api/creator/dashboard')->assertUnauthorized();
});

test('the creator dashboard summarizes the artists catalog', function () {
    $user = User::factory()->create();
    $user->assignRole('creator');
    $artist = Artist::factory()->create(['user_id' => $user->id]);
    \App\Models\Track::factory()->for($artist)->count(3)->create(['plays_count' => 10]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/creator/dashboard');

    $response->assertOk();
    expect($response->json('total_tracks'))->toBe(3);
    expect($response->json('total_streams'))->toBe(30);
});
