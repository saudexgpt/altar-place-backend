<?php

use App\Models\Artist;
use App\Models\DownloadEvent;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('creator analytics aggregates streams, unique listeners, followers, and downloads', function () {
    $creator = User::factory()->create();
    $creator->assignRole('creator');
    $artist = Artist::factory()->create(['user_id' => $creator->id]);

    $trackA = Track::factory()->for($artist)->create(['plays_count' => 10, 'duration_seconds' => 60]);
    $trackB = Track::factory()->for($artist)->create(['plays_count' => 5, 'duration_seconds' => 120]);

    $listenerOne = User::factory()->create();
    $listenerTwo = User::factory()->create();

    PlayHistory::create(['user_id' => $listenerOne->id, 'track_id' => $trackA->id, 'played_at' => now()]);
    PlayHistory::create(['user_id' => $listenerTwo->id, 'track_id' => $trackA->id, 'played_at' => now()]);
    PlayHistory::create(['user_id' => $listenerOne->id, 'track_id' => $trackB->id, 'played_at' => now()]);

    DownloadEvent::create(['user_id' => $listenerOne->id, 'track_id' => $trackA->id]);

    $follower = User::factory()->create();
    $follower->follows()->create(['followable_type' => Artist::class, 'followable_id' => $artist->id]);

    $response = $this->actingAs($creator, 'sanctum')->getJson('/api/creator/analytics');

    $response->assertOk();
    expect($response->json('streams'))->toBe(15);
    expect($response->json('unique_listeners'))->toBe(2);
    expect($response->json('followers'))->toBe(1);
    expect($response->json('downloads'))->toBe(1);
    expect($response->json('revenue'))->toBeNull();
    expect($response->json('tracks'))->toHaveCount(2);
});

test('analytics only reflect the authenticated creators own tracks', function () {
    $creatorA = User::factory()->create();
    $creatorA->assignRole('creator');
    $artistA = Artist::factory()->create(['user_id' => $creatorA->id]);
    Track::factory()->for($artistA)->create(['plays_count' => 100]);

    $creatorB = User::factory()->create();
    $creatorB->assignRole('creator');
    Artist::factory()->create(['user_id' => $creatorB->id]);

    $response = $this->actingAs($creatorB, 'sanctum')->getJson('/api/creator/analytics');

    $response->assertOk();
    expect($response->json('streams'))->toBe(0);
});
