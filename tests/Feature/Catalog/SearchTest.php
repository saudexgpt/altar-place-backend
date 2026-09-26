<?php

use App\Models\Artist;
use App\Models\Track;

test('search returns grouped results across categories by default', function () {
    $artist = Artist::factory()->create(['name' => 'Wonderful Grace']);
    Track::factory()->for($artist)->create(['title' => 'Wonderful Day', 'type' => 'music']);

    $response = $this->getJson('/api/search?q=Wonderful');

    $response->assertOk();
    $response->assertJsonStructure(['artists', 'tracks', 'podcasts', 'sermons', 'playlists', 'genres']);
    expect($response->json('artists'))->toHaveCount(1);
    expect($response->json('tracks'))->toHaveCount(1);
});

test('search can be scoped to a single type', function () {
    $artist = Artist::factory()->create(['name' => 'Faithful Singer']);
    Track::factory()->for($artist)->create(['title' => 'Faithful Song', 'type' => 'music']);

    $response = $this->getJson('/api/search?q=Faithful&type=artist');

    $response->assertOk();
    expect($response->json())->toHaveKey('artists');
    expect($response->json())->not->toHaveKey('tracks');
});

test('search requires a query term', function () {
    $response = $this->getJson('/api/search');

    $response->assertUnprocessable();
});
