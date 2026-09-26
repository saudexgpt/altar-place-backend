<?php

use App\Models\Activity;
use App\Models\Playlist;
use App\Models\Share;
use App\Models\Track;
use App\Models\User;

test('sharing a track logs a share event and an activity entry', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/share", ['platform' => 'whatsapp']);

    $response->assertCreated();
    expect(Share::where('shareable_id', $track->id)->where('shareable_type', Track::class)->count())->toBe(1);
    expect(Share::first()->platform)->toBe('whatsapp');
    expect(Activity::where('user_id', $user->id)->where('type', Activity::TYPE_SHARED_TRACK)->count())->toBe(1);
});

test('sharing a track without a platform is allowed', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/share")->assertCreated();
});

test('sharing a playlist logs a share event', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/playlists/{$playlist->id}/share", ['platform' => 'twitter'])->assertCreated();

    expect(Share::where('shareable_id', $playlist->id)->where('shareable_type', Playlist::class)->count())->toBe(1);
});

test('shares_count and comments_count are exposed when viewing a track', function () {
    $track = Track::factory()->create();
    Share::create(['user_id' => User::factory()->create()->id, 'shareable_type' => Track::class, 'shareable_id' => $track->id]);
    Share::create(['user_id' => User::factory()->create()->id, 'shareable_type' => Track::class, 'shareable_id' => $track->id]);

    $response = $this->getJson("/api/tracks/{$track->id}");

    $response->assertOk();
    expect($response->json('data.shares_count') ?? $response->json('shares_count'))->toBe(2);
    expect($response->json('data.comments_count') ?? $response->json('comments_count'))->toBe(0);
});

test('shares_count is exposed when viewing a playlist', function () {
    $playlist = Playlist::factory()->create(['is_public' => true]);
    Share::create(['user_id' => User::factory()->create()->id, 'shareable_type' => Playlist::class, 'shareable_id' => $playlist->id]);

    $response = $this->getJson("/api/playlists/{$playlist->id}");

    $response->assertOk();
    expect($response->json('data.shares_count') ?? $response->json('shares_count'))->toBe(1);
});
