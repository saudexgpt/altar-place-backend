<?php

use App\Models\Artist;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Track;

test('guests can browse trending tracks', function () {
    $artist = Artist::factory()->create();
    Track::factory()->for($artist)->create(['plays_count' => 100]);
    Track::factory()->for($artist)->create(['plays_count' => 500]);

    $response = $this->getJson('/api/discovery/trending');

    $response->assertOk();
    expect($response->json('data.0.plays_count'))->toBe(500);
});

test('trending can be filtered by category', function () {
    $artist = Artist::factory()->create();
    Track::factory()->for($artist)->create(['type' => 'music']);
    Track::factory()->for($artist)->create(['type' => 'sermon']);

    $response = $this->getJson('/api/discovery/trending?category=the-word');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.type'))->toBe('sermon');
});

test('new releases are ordered by release date', function () {
    $artist = Artist::factory()->create();
    Track::factory()->for($artist)->create(['release_date' => now()->subDays(10)]);
    Track::factory()->for($artist)->create(['release_date' => now()->subDay()]);

    $response = $this->getJson('/api/discovery/new-releases');

    $response->assertOk();
    expect($response->json('data.0.release_date'))->toBe(now()->subDay()->toDateString());
});

test('featured artists only include verified artists', function () {
    Artist::factory()->create(['is_verified' => true]);
    Artist::factory()->create(['is_verified' => false]);

    $response = $this->getJson('/api/discovery/featured-artists');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('popular playlists only include curated public playlists', function () {
    Playlist::factory()->create(['is_curated' => true, 'is_public' => true]);
    Playlist::factory()->create(['is_curated' => false, 'is_public' => true]);

    $response = $this->getJson('/api/discovery/popular-playlists');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('genres are listed alphabetically', function () {
    Genre::create(['name' => 'Worship', 'slug' => 'worship']);
    Genre::create(['name' => 'Afrobeat', 'slug' => 'afrobeat']);

    $response = $this->getJson('/api/genres');

    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('Afrobeat');
});
