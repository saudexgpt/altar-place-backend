<?php

use App\Models\Artist;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;
use App\Support\WavGenerator;
use Illuminate\Support\Facades\Storage;

function seedPlayableTrack(): Track
{
    Storage::fake('audio');
    Storage::disk('audio')->makeDirectory('audio');

    $track = Track::factory()->for(Artist::factory())->create([
        'audio_path' => 'audio/test-track.wav',
        'plays_count' => 0,
    ]);

    WavGenerator::make(Storage::disk('audio')->path($track->audio_path), 2, 440.0);

    return $track;
}

test('a track can be streamed in full without a range header', function () {
    $track = seedPlayableTrack();

    $response = $this->get("/api/tracks/{$track->id}/stream");

    $response->assertOk();
    $response->assertHeader('Accept-Ranges', 'bytes');
    expect($track->fresh()->plays_count)->toBe(1);
});

test('a range request returns partial content and does not double count plays', function () {
    $track = seedPlayableTrack();

    $this->get("/api/tracks/{$track->id}/stream");
    $partial = $this->get("/api/tracks/{$track->id}/stream", ['Range' => 'bytes=1000-2000']);

    $partial->assertStatus(206);
    $partial->assertHeader('Content-Range');
    expect($track->fresh()->plays_count)->toBe(1);
});

test('streaming while authenticated logs play history', function () {
    $track = seedPlayableTrack();
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->get("/api/tracks/{$track->id}/stream");

    expect(PlayHistory::where('user_id', $user->id)->where('track_id', $track->id)->exists())->toBeTrue();
});

test('streaming a missing audio file 404s', function () {
    $track = Track::factory()->for(Artist::factory())->create([
        'audio_path' => 'audio/does-not-exist.wav',
    ]);

    $response = $this->get("/api/tracks/{$track->id}/stream");

    $response->assertNotFound();
});
