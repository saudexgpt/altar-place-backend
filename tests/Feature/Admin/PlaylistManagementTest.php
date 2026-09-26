<?php

use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function playlistModerator(): User
{
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    return $moderator;
}

test('a moderator can create a curated playlist', function () {
    $response = $this->actingAs(playlistModerator(), 'sanctum')->postJson('/api/playlists', [
        'title' => 'Top Worship',
        'is_public' => true,
        'is_curated' => true,
    ]);

    $response->assertCreated();
    expect(Playlist::where('title', 'Top Worship')->first()->is_curated)->toBeTrue();
});

test('a plain listener cannot mark their own playlist as curated', function () {
    $listener = User::factory()->create();
    $listener->assignRole('listener');

    $response = $this->actingAs($listener, 'sanctum')->postJson('/api/playlists', [
        'title' => 'My Mix',
        'is_curated' => true,
    ]);

    $response->assertCreated();
    expect(Playlist::where('title', 'My Mix')->first()->is_curated)->toBeFalse();
});

test('a moderator can edit and delete any curated playlist', function () {
    $owner = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id, 'title' => 'Old Name', 'is_curated' => true]);

    $update = $this->actingAs(playlistModerator(), 'sanctum')->putJson("/api/playlists/{$playlist->id}", [
        'title' => 'New Name',
    ]);
    $update->assertOk();
    expect($playlist->fresh()->title)->toBe('New Name');

    $delete = $this->actingAs(playlistModerator(), 'sanctum')->deleteJson("/api/playlists/{$playlist->id}");
    $delete->assertOk();
    expect(Playlist::find($playlist->id))->toBeNull();
});

test('the owner of a curated playlist still cannot edit it themselves', function () {
    $owner = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id, 'is_curated' => true]);

    $this->actingAs($owner, 'sanctum')->putJson("/api/playlists/{$playlist->id}", [
        'title' => 'Hijacked',
    ])->assertForbidden();
});

test('a moderator can add and remove tracks on any playlist, including curated ones', function () {
    $owner = User::factory()->create();
    $playlist = Playlist::factory()->create(['user_id' => $owner->id, 'is_curated' => true]);
    $track = Track::factory()->for(Artist::factory())->create();

    $add = $this->actingAs(playlistModerator(), 'sanctum')->postJson("/api/playlists/{$playlist->id}/tracks/{$track->id}");
    $add->assertOk();
    expect($playlist->fresh()->tracks()->pluck('tracks.id'))->toContain($track->id);

    $remove = $this->actingAs(playlistModerator(), 'sanctum')->deleteJson("/api/playlists/{$playlist->id}/tracks/{$track->id}");
    $remove->assertOk();
    expect($playlist->fresh()->tracks()->pluck('tracks.id'))->not->toContain($track->id);
});
