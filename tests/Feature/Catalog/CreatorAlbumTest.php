<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function creatorWithArtist(): array
{
    $user = User::factory()->create();
    $user->assignRole('creator');
    $artist = Artist::factory()->create(['user_id' => $user->id]);

    return [$user, $artist];
}

test('a creator can create an album', function () {
    [$user] = creatorWithArtist();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/creator/albums', [
        'title' => 'Live Sessions',
        'type' => 'album',
        'release_year' => 2026,
    ]);

    $response->assertCreated();
    expect($response->json('data.title'))->toBe('Live Sessions');
});

test('a creator can update and delete their own album', function () {
    [$user, $artist] = creatorWithArtist();
    $album = Album::factory()->for($artist)->create();

    $update = $this->actingAs($user, 'sanctum')->putJson("/api/creator/albums/{$album->id}", [
        'title' => 'Renamed Album',
    ]);
    $update->assertOk();
    expect($update->json('data.title'))->toBe('Renamed Album');

    $this->actingAs($user, 'sanctum')->deleteJson("/api/creator/albums/{$album->id}")->assertOk();
    expect(Album::find($album->id))->toBeNull();
});

test('deleting an album does not delete its tracks', function () {
    [$user, $artist] = creatorWithArtist();
    $album = Album::factory()->for($artist)->create();
    $track = \App\Models\Track::factory()->for($artist)->for($album)->create();

    $this->actingAs($user, 'sanctum')->deleteJson("/api/creator/albums/{$album->id}")->assertOk();

    expect(\App\Models\Track::find($track->id))->not->toBeNull();
    expect(\App\Models\Track::find($track->id)->album_id)->toBeNull();
});

test('a creator cannot update another creators album', function () {
    [, $artistA] = creatorWithArtist();
    [$userB] = creatorWithArtist();
    $album = Album::factory()->for($artistA)->create();

    $this->actingAs($userB, 'sanctum')
        ->putJson("/api/creator/albums/{$album->id}", ['title' => 'Hijacked'])
        ->assertForbidden();
});
