<?php

use App\Jobs\TranscodeAudioJob;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a moderator can publish a platform resource without a personal creator profile', function () {
    Storage::fake('audio');
    Storage::fake('public');
    Queue::fake();

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $response = $this->actingAs($moderator, 'sanctum')->postJson('/api/admin/tracks', [
        'title' => 'Sunday Sermon',
        'type' => 'sermon',
        'language' => 'English',
        'audio' => UploadedFile::fake()->create('sermon.mp3', 500, 'audio/mpeg'),
        'cover' => UploadedFile::fake()->image('cover.jpg'),
    ]);

    $response->assertCreated();
    expect($response->json('data.title'))->toBe('Sunday Sermon');
    expect($response->json('data.artist.name'))->toBe('Altar Place');
    expect($response->json('data.transcoding_status'))->toBe('pending');

    Queue::assertPushed(TranscodeAudioJob::class);
    Storage::disk('audio')->assertExists(Track::first()->audio_path);
});

test('repeated admin uploads share the same official artist', function () {
    Storage::fake('audio');
    Queue::fake();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    foreach (['Track One', 'Track Two'] as $title) {
        $this->actingAs($superAdmin, 'sanctum')->postJson('/api/admin/tracks', [
            'title' => $title,
            'type' => 'music',
            'audio' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
        ])->assertCreated();
    }

    expect(Artist::where('slug', 'altarplace-official')->count())->toBe(1);
    expect(Track::where('artist_id', Artist::official()->id)->count())->toBe(2);
});

test('a listener cannot publish a platform resource', function () {
    $user = User::factory()->create();
    $user->assignRole('listener');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/admin/tracks', [
        'title' => 'Sneaky Upload',
        'type' => 'music',
        'audio' => UploadedFile::fake()->create('song.mp3', 500, 'audio/mpeg'),
    ]);

    $response->assertForbidden();
});
