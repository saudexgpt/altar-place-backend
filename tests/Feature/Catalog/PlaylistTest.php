<?php

use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;

test('a user can create a playlist with sensible defaults', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/playlists', ['title' => 'Sunday Mix']);

    $response->assertCreated();
    expect($response->json('data.is_public'))->toBeTrue();
    expect($response->json('data.is_curated'))->toBeFalse();
});

test('a user can add and remove tracks from their own playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->for($user)->create();
    $track = Track::factory()->for(Artist::factory())->create();

    $add = $this->actingAs($user, 'sanctum')->postJson("/api/playlists/{$playlist->id}/tracks/{$track->id}");
    $add->assertOk();
    expect($add->json('data.tracks'))->toHaveCount(1);

    $remove = $this->actingAs($user, 'sanctum')->deleteJson("/api/playlists/{$playlist->id}/tracks/{$track->id}");
    $remove->assertOk();
    expect($remove->json('data.tracks'))->toHaveCount(0);
});

test('a user cannot modify another users playlist', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $playlist = Playlist::factory()->for($owner)->create();
    $track = Track::factory()->for(Artist::factory())->create();

    $this->actingAs($intruder, 'sanctum')
        ->postJson("/api/playlists/{$playlist->id}/tracks/{$track->id}")
        ->assertForbidden();

    $this->actingAs($intruder, 'sanctum')
        ->putJson("/api/playlists/{$playlist->id}", ['title' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($intruder, 'sanctum')
        ->deleteJson("/api/playlists/{$playlist->id}")
        ->assertForbidden();
});

test('a curated playlist cannot be edited even by its owning user record', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->for($user)->create(['is_curated' => true]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/playlists/{$playlist->id}", ['title' => 'New Title'])
        ->assertForbidden();
});

test('a private playlist is not visible to other users', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $playlist = Playlist::factory()->for($owner)->create(['is_public' => false]);

    $this->actingAs($stranger, 'sanctum')->getJson("/api/playlists/{$playlist->id}")->assertForbidden();
    $this->actingAs($owner, 'sanctum')->getJson("/api/playlists/{$playlist->id}")->assertOk();
});

test('guests can view a public playlist', function () {
    $playlist = Playlist::factory()->for(User::factory())->create(['is_public' => true]);

    $this->getJson("/api/playlists/{$playlist->id}")->assertOk();
});

test('a user can follow and unfollow a playlist', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->for(User::factory())->create(['is_public' => true]);

    $this->actingAs($user, 'sanctum')->postJson("/api/playlists/{$playlist->id}/follow")->assertOk();
    expect($user->follows()->where('followable_type', Playlist::class)->count())->toBe(1);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/playlists/{$playlist->id}/follow")->assertOk();
    expect($user->follows()->where('followable_type', Playlist::class)->count())->toBe(0);
});
