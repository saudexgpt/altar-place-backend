<?php

use App\Models\Comment;
use App\Models\Report;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function contentModerator(): User
{
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    return $moderator;
}

test('a listener cannot access moderation routes', function () {
    $listener = User::factory()->create();
    $listener->assignRole('listener');

    $this->actingAs($listener, 'sanctum')->getJson('/api/admin/tracks')->assertForbidden();
    $this->actingAs($listener, 'sanctum')->getJson('/api/admin/reports')->assertForbidden();
});

test('a moderator can reject a track, which then disappears from search and discovery', function () {
    $track = Track::factory()->create(['title' => 'Suspicious Upload', 'plays_count' => 999]);

    $reject = $this->actingAs(contentModerator(), 'sanctum')->postJson("/api/admin/tracks/{$track->id}/reject", [
        'reason' => 'Copyright violation.',
    ]);
    $reject->assertOk();
    expect($track->fresh()->status)->toBe('rejected');

    $search = $this->getJson('/api/search?q=Suspicious');
    expect(collect($search->json('tracks.data'))->pluck('id'))->not->toContain($track->id);

    $trending = $this->getJson('/api/discovery/trending');
    expect(collect($trending->json('data'))->pluck('id'))->not->toContain($track->id);
});

test('a rejected track is hidden from guests but visible to a moderator', function () {
    $track = Track::factory()->create(['status' => 'rejected', 'rejection_reason' => 'Copyright violation.']);

    $this->getJson("/api/tracks/{$track->id}")->assertNotFound();
    $this->getJson("/api/tracks/{$track->id}/stream")->assertNotFound();

    $moderatorView = $this->actingAs(contentModerator(), 'sanctum')->getJson("/api/tracks/{$track->id}");
    $moderatorView->assertOk();
});

test('the track list still serializes correctly after a track has been moderated', function () {
    $track = Track::factory()->create();
    $moderator = contentModerator();

    $this->actingAs($moderator, 'sanctum')->postJson("/api/admin/tracks/{$track->id}/reject", [
        'reason' => 'x',
    ])->assertOk();

    $response = $this->actingAs($moderator, 'sanctum')->getJson('/api/admin/tracks');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $track->id);
    expect($row['moderated_at'])->not->toBeNull();
});

test('the track list includes a playable stream url', function () {
    $track = Track::factory()->create();

    $response = $this->actingAs(contentModerator(), 'sanctum')->getJson('/api/admin/tracks');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $track->id);
    expect($row['stream_url'])->toContain("/tracks/{$track->id}/stream");
});

test('a moderator can edit any track\'s metadata, not just their own', function () {
    $track = Track::factory()->create(['title' => 'Old Title']);

    $response = $this->actingAs(contentModerator(), 'sanctum')->putJson("/api/admin/tracks/{$track->id}", [
        'title' => 'Corrected Title',
    ]);

    $response->assertOk();
    expect($track->fresh()->title)->toBe('Corrected Title');
});

test('a listener cannot edit a track through the admin endpoint', function () {
    $listener = User::factory()->create();
    $listener->assignRole('listener');
    $track = Track::factory()->create();

    $this->actingAs($listener, 'sanctum')->putJson("/api/admin/tracks/{$track->id}", [
        'title' => 'Hijacked',
    ])->assertForbidden();
});

test('the track list can be filtered by content type', function () {
    Track::factory()->create(['type' => 'music', 'title' => 'A Worship Song']);
    Track::factory()->create(['type' => 'sermon', 'title' => 'A Sunday Sermon']);

    $response = $this->actingAs(contentModerator(), 'sanctum')->getJson('/api/admin/tracks?type=sermon');

    $response->assertOk();
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('A Sunday Sermon');
    expect($titles)->not->toContain('A Worship Song');
});

test('a moderator can re-approve a rejected track', function () {
    $track = Track::factory()->create(['status' => 'rejected', 'rejection_reason' => 'x']);

    $response = $this->actingAs(contentModerator(), 'sanctum')->postJson("/api/admin/tracks/{$track->id}/approve");

    $response->assertOk();
    expect($track->fresh()->status)->toBe('approved');
    expect($track->fresh()->rejection_reason)->toBeNull();
});

test('reports listing includes the report count on tracks', function () {
    $track = Track::factory()->create();
    $reporter = User::factory()->create();

    Report::create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Track::class,
        'reportable_id' => $track->id,
        'reason' => 'spam',
    ]);

    $response = $this->actingAs(contentModerator(), 'sanctum')->getJson('/api/admin/tracks');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $track->id);
    expect($row['reports_count'])->toBe(1);
});

test('dismissing a report leaves the reported track untouched', function () {
    $track = Track::factory()->create();
    $reporter = User::factory()->create();

    $report = Report::create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Track::class,
        'reportable_id' => $track->id,
        'reason' => 'other',
    ]);

    $response = $this->actingAs(contentModerator(), 'sanctum')->postJson("/api/admin/reports/{$report->id}/resolve", [
        'action' => 'dismiss',
    ]);

    $response->assertOk();
    expect($report->fresh()->status)->toBe('dismissed');
    expect($track->fresh()->status)->toBe('approved');
});

test('actioning a report on a track rejects it', function () {
    $track = Track::factory()->create();
    $reporter = User::factory()->create();

    $report = Report::create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Track::class,
        'reportable_id' => $track->id,
        'reason' => 'copyright',
    ]);

    $response = $this->actingAs(contentModerator(), 'sanctum')->postJson("/api/admin/reports/{$report->id}/resolve", [
        'action' => 'action',
        'note' => 'Confirmed rights holder complaint.',
    ]);

    $response->assertOk();
    expect($report->fresh()->status)->toBe('actioned');
    expect($track->fresh()->status)->toBe('rejected');
});

test('actioning a report on a comment deletes it', function () {
    $track = Track::factory()->create();
    $author = User::factory()->create();
    $comment = $track->comments()->create(['user_id' => $author->id, 'body' => 'spammy link']);
    $reporter = User::factory()->create();

    $report = Report::create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Comment::class,
        'reportable_id' => $comment->id,
        'reason' => 'spam',
    ]);

    $response = $this->actingAs(contentModerator(), 'sanctum')->postJson("/api/admin/reports/{$report->id}/resolve", [
        'action' => 'action',
    ]);

    $response->assertOk();
    expect(Comment::find($comment->id))->toBeNull();
});
