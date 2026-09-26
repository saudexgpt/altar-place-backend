<?php

use App\Models\Activity;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;

test('following an artist, favoriting a track, and creating a playlist all appear in the actor\'s own activity feed', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();
    $track = Track::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/favorite")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/playlists', ['title' => 'My Mix'])->assertCreated();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/activity');

    $response->assertOk();
    $types = collect($response->json('data'))->pluck('type');

    expect($types)->toContain(Activity::TYPE_FOLLOWED_ARTIST);
    expect($types)->toContain(Activity::TYPE_FAVORITED_TRACK);
    expect($types)->toContain(Activity::TYPE_CREATED_PLAYLIST);
});

test('the activity feed is scoped to the current user only', function () {
    $me = User::factory()->create();
    $someoneElse = User::factory()->create();
    $artist = Artist::factory()->create();

    $this->actingAs($someoneElse, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    $response = $this->actingAs($me, 'sanctum')->getJson('/api/activity');

    $response->assertOk();
    expect($response->json('data'))->toBeEmpty();
});

test('re-following an already-followed artist does not create a duplicate activity entry', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    expect(Activity::where('user_id', $user->id)->where('type', Activity::TYPE_FOLLOWED_ARTIST)->count())->toBe(1);
});

test('commenting on and sharing a track are both logged as activity', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/comments", ['body' => 'Nice!'])->assertCreated();
    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/share")->assertCreated();

    $types = Activity::where('user_id', $user->id)->pluck('type');
    expect($types)->toContain(Activity::TYPE_COMMENTED_ON_TRACK);
    expect($types)->toContain(Activity::TYPE_SHARED_TRACK);
});
