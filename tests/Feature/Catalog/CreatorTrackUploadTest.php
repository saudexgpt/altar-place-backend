<?php

use App\Jobs\TranscodeAudioJob;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function creatorUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('creator');
    Artist::factory()->create(['user_id' => $user->id, 'name' => 'Test Creator']);

    return $user;
}

test('a creator can upload a track with metadata, artwork, and tags', function () {
    Storage::fake('audio');
    Storage::fake('public');
    Queue::fake();

    $user = creatorUser();
    $genre = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/creator/tracks', [
        'title' => 'New Single',
        'description' => 'A brand new worship single.',
        'type' => 'music',
        'genre_id' => $genre->id,
        'language' => 'English',
        'is_explicit' => false,
        'tags' => ['worship', 'live'],
        'audio' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
        'cover' => UploadedFile::fake()->image('cover.jpg'),
    ]);

    $response->assertCreated();
    expect($response->json('data.title'))->toBe('New Single');
    expect($response->json('data.transcoding_status'))->toBe('pending');
    expect($response->json('data.tags'))->toHaveCount(2);
    expect($response->json('data.cover_url'))->not->toBeNull();

    Queue::assertPushed(TranscodeAudioJob::class);
    Storage::disk('audio')->assertExists(Track::first()->audio_path);
});

test('a listener without a creator profile cannot upload', function () {
    $user = User::factory()->create();
    $user->assignRole('listener');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/creator/tracks', [
        'title' => 'New Single',
        'type' => 'music',
        'audio' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
    ]);

    $response->assertForbidden();
});

test('track upload requires a valid audio file', function () {
    $user = creatorUser();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/creator/tracks', [
        'title' => 'New Single',
        'type' => 'music',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('audio');
});

test('a creator can edit their own track metadata', function () {
    Storage::fake('public');
    $user = creatorUser();
    $artist = Artist::where('user_id', $user->id)->first();
    $track = Track::factory()->for($artist)->create(['title' => 'Old Title']);

    $response = $this->actingAs($user, 'sanctum')->putJson("/api/creator/tracks/{$track->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertOk();
    expect($response->json('data.title'))->toBe('Updated Title');
});

test('a creator cannot edit another creators track', function () {
    $owner = creatorUser();
    $intruder = creatorUser();
    $ownerArtist = Artist::where('user_id', $owner->id)->first();
    $track = Track::factory()->for($ownerArtist)->create();

    $this->actingAs($intruder, 'sanctum')
        ->putJson("/api/creator/tracks/{$track->id}", ['title' => 'Hijacked'])
        ->assertForbidden();
});

test('a creator can delete their own track', function () {
    Storage::fake('audio');
    $user = creatorUser();
    $artist = Artist::where('user_id', $user->id)->first();
    $track = Track::factory()->for($artist)->create(['audio_path' => 'audio/does-not-matter.wav']);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/creator/tracks/{$track->id}")->assertOk();

    expect(Track::find($track->id))->toBeNull();
});
