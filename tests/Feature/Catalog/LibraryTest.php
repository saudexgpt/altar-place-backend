<?php

use App\Models\Artist;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;

test('an authenticated user can favorite and unfavorite a track', function () {
    $user = User::factory()->create();
    $track = Track::factory()->for(Artist::factory())->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/favorite")->assertOk();

    $favorites = $this->actingAs($user, 'sanctum')->getJson('/api/library/favorites');
    $favorites->assertOk();
    expect($favorites->json('data'))->toHaveCount(1);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/tracks/{$track->id}/favorite")->assertOk();

    $favoritesAfter = $this->actingAs($user, 'sanctum')->getJson('/api/library/favorites');
    expect($favoritesAfter->json('data'))->toHaveCount(0);
});

test('favoriting the same track twice does not create duplicates', function () {
    $user = User::factory()->create();
    $track = Track::factory()->for(Artist::factory())->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/favorite")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/favorite")->assertOk();

    expect($user->favorites()->count())->toBe(1);
});

test('recently played returns tracks in most-recent-first order without duplicates', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();
    $trackA = Track::factory()->for($artist)->create();
    $trackB = Track::factory()->for($artist)->create();

    PlayHistory::create(['user_id' => $user->id, 'track_id' => $trackA->id, 'played_at' => now()->subMinutes(10)]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $trackB->id, 'played_at' => now()->subMinutes(5)]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $trackA->id, 'played_at' => now()]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/library/recently-played');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids->all())->toBe([$trackA->id, $trackB->id]);
});

test('a users own playlists show up in their library', function () {
    $user = User::factory()->create();
    $user->playlists()->create(['title' => 'My Mix']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/library/playlists');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('favorites require authentication', function () {
    $track = Track::factory()->for(Artist::factory())->create();

    $this->postJson("/api/tracks/{$track->id}/favorite")->assertUnauthorized();
});
