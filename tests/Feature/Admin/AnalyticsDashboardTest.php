<?php

use App\Models\Artist;
use App\Models\Favorite;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function dashboardModerator(): User
{
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    return $moderator;
}

test('overview reports music/word content counts and total plays', function () {
    Track::factory()->create(['type' => 'music', 'plays_count' => 100]);
    Track::factory()->create(['type' => 'sermon', 'plays_count' => 50]);
    Track::factory()->create(['type' => 'podcast', 'plays_count' => 25]);

    $response = $this->actingAs(dashboardModerator(), 'sanctum')->getJson('/api/admin/analytics/overview');

    $response->assertOk();
    expect($response->json('music_tracks_count'))->toBe(1);
    expect($response->json('word_tracks_count'))->toBe(2);
    expect($response->json('total_plays'))->toBe(175);
});

test('the chart endpoint returns a daily music vs word plays series', function () {
    Carbon::setTestNow('2026-09-14 12:00:00');

    $musicTrack = Track::factory()->for(Artist::factory())->create(['type' => 'music']);
    $sermonTrack = Track::factory()->for(Artist::factory())->create(['type' => 'sermon']);
    $user = User::factory()->create();

    PlayHistory::create(['user_id' => $user->id, 'track_id' => $musicTrack->id, 'played_at' => now()]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $musicTrack->id, 'played_at' => now()]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $sermonTrack->id, 'played_at' => now()->subDay()]);

    $response = $this->actingAs(dashboardModerator(), 'sanctum')->getJson('/api/admin/analytics/chart?days=7');

    $response->assertOk();
    expect($response->json('labels'))->toHaveCount(7);
    expect(array_sum($response->json('music_plays')))->toBe(2);
    expect(array_sum($response->json('word_plays')))->toBe(1);

    Carbon::setTestNow();
});

test('top content ranks by plays or by favorites depending on the metric', function () {
    $popular = Track::factory()->create(['plays_count' => 500]);
    $unpopular = Track::factory()->create(['plays_count' => 1]);
    $mostLiked = Track::factory()->create(['plays_count' => 1]);

    $liker = User::factory()->create();
    Favorite::create(['user_id' => $liker->id, 'favoritable_type' => Track::class, 'favoritable_id' => $mostLiked->id]);

    $byPlays = $this->actingAs(dashboardModerator(), 'sanctum')->getJson('/api/admin/analytics/top-content?metric=played&limit=2');
    expect($byPlays->json('data.0.id'))->toBe($popular->id);

    $byLikes = $this->actingAs(dashboardModerator(), 'sanctum')->getJson('/api/admin/analytics/top-content?metric=liked&limit=1');
    expect($byLikes->json('data.0.id'))->toBe($mostLiked->id);
});

test('top content with a days window ranks by recent activity, not all-time totals', function () {
    Carbon::setTestNow('2026-09-15 12:00:00');

    // All-time this one has the most plays_count, but none of them recent.
    $allTimePopular = Track::factory()->for(Artist::factory())->create(['plays_count' => 500]);
    // This one has fewer all-time plays but was actually played this week.
    $recentlyPopular = Track::factory()->for(Artist::factory())->create(['plays_count' => 10]);

    $user = User::factory()->create();
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $recentlyPopular->id, 'played_at' => now()->subDay()]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $recentlyPopular->id, 'played_at' => now()->subDays(2)]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $allTimePopular->id, 'played_at' => now()->subDays(30)]);

    $withoutWindow = $this->actingAs(dashboardModerator(), 'sanctum')->getJson('/api/admin/analytics/top-content?metric=played&limit=1');
    expect($withoutWindow->json('data.0.id'))->toBe($allTimePopular->id);

    $withWindow = $this->actingAs(dashboardModerator(), 'sanctum')->getJson('/api/admin/analytics/top-content?metric=played&limit=1&days=7');
    expect($withWindow->json('data.0.id'))->toBe($recentlyPopular->id);

    Carbon::setTestNow();
});

test('recent activity merges new users, uploads, and playlist changes by recency', function () {
    Carbon::setTestNow('2026-09-14 12:00:00');
    $moderator = dashboardModerator();

    $user = User::factory()->create(['created_at' => now()->subMinutes(3)]);
    $track = Track::factory()->for(Artist::factory())->create(['title' => 'Brand New Track', 'created_at' => now()->subMinutes(2)]);
    Playlist::factory()->create(['user_id' => $user->id, 'title' => 'Morning Worship', 'updated_at' => now()->addMinute()]);

    $response = $this->actingAs($moderator, 'sanctum')->getJson('/api/admin/analytics/recent-activity?limit=10');

    $response->assertOk();
    $types = collect($response->json('data'))->pluck('type');
    expect($types)->toContain('user_registered');
    expect($types)->toContain('song_uploaded');
    expect($types)->toContain('playlist_updated');

    // Most recent first: the playlist update (now()) should lead.
    expect($response->json('data.0.type'))->toBe('playlist_updated');

    Carbon::setTestNow();
});

test('a listener cannot access the analytics chart or top content', function () {
    $listener = User::factory()->create();
    $listener->assignRole('listener');

    $this->actingAs($listener, 'sanctum')->getJson('/api/admin/analytics/chart')->assertForbidden();
    $this->actingAs($listener, 'sanctum')->getJson('/api/admin/analytics/top-content')->assertForbidden();
    $this->actingAs($listener, 'sanctum')->getJson('/api/admin/analytics/recent-activity')->assertForbidden();
});
