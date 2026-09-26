<?php

use App\Models\Artist;
use App\Models\Track;
use App\Models\User;

test('following an artist creates a database notification for the artist\'s owning user', function () {
    $artistOwner = User::factory()->create();
    $artist = Artist::factory()->create(['user_id' => $artistOwner->id]);
    $follower = User::factory()->create();

    $this->actingAs($follower, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    expect($artistOwner->notifications()->count())->toBe(1);
    expect($artistOwner->unreadNotifications()->count())->toBe(1);
});

test('following your own artist profile does not notify yourself', function () {
    $owner = User::factory()->create();
    $artist = Artist::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    expect($owner->notifications()->count())->toBe(0);
});

test('a user can list their notifications with an unread count', function () {
    $artistOwner = User::factory()->create();
    $artist = Artist::factory()->create(['user_id' => $artistOwner->id]);
    $track = Track::factory()->create(['artist_id' => $artist->id]);

    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/tracks/{$track->id}/comments", ['body' => 'Hi!'])->assertCreated();

    $response = $this->actingAs($artistOwner, 'sanctum')->getJson('/api/notifications');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('unread_count'))->toBe(2);
});

test('marking a single notification as read updates its status and the unread count', function () {
    $artistOwner = User::factory()->create();
    $artist = Artist::factory()->create(['user_id' => $artistOwner->id]);

    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    $notificationId = $artistOwner->notifications()->first()->id;

    $this->actingAs($artistOwner, 'sanctum')->postJson("/api/notifications/{$notificationId}/read")->assertOk();

    expect($artistOwner->unreadNotifications()->count())->toBe(0);
});

test('a user cannot mark another user\'s notification as read', function () {
    $artistOwner = User::factory()->create();
    $artist = Artist::factory()->create(['user_id' => $artistOwner->id]);
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    $notificationId = $artistOwner->notifications()->first()->id;
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'sanctum')->postJson("/api/notifications/{$notificationId}/read")->assertNotFound();
});

test('mark-all-as-read clears every unread notification for the user', function () {
    $artistOwner = User::factory()->create();
    $artist = Artist::factory()->create(['user_id' => $artistOwner->id]);
    $track = Track::factory()->create(['artist_id' => $artist->id]);

    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();
    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/tracks/{$track->id}/comments", ['body' => 'Hi!'])->assertCreated();

    $this->actingAs($artistOwner, 'sanctum')->postJson('/api/notifications/read-all')->assertOk();

    expect($artistOwner->unreadNotifications()->count())->toBe(0);
});
