<?php

use App\Models\Artist;
use App\Models\Comment;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewCommentNotification;

test('a guest can read comments on a track', function () {
    $track = Track::factory()->create();
    Comment::create(['user_id' => User::factory()->create()->id, 'commentable_type' => Track::class, 'commentable_id' => $track->id, 'body' => 'Great track!']);

    $response = $this->getJson("/api/tracks/{$track->id}/comments");

    $response->assertOk();
    expect($response->json('data.0.body'))->toBe('Great track!');
});

test('an authenticated user can post a comment on a track', function () {
    Notification::fake();

    $user = User::factory()->create();
    $track = Track::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/comments", [
        'body' => 'This blessed me today.',
    ]);

    $response->assertCreated();
    expect($response->json('data.body'))->toBe('This blessed me today.');
    expect($response->json('data.user.id'))->toBe($user->id);
    expect(Comment::where('commentable_id', $track->id)->count())->toBe(1);
});

test('an empty comment body is rejected', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/tracks/{$track->id}/comments", ['body' => ''])
        ->assertStatus(422);
});

test('a user can delete their own comment but not someone else\'s', function () {
    $author = User::factory()->create();
    $intruder = User::factory()->create();
    $track = Track::factory()->create();

    $comment = Comment::create([
        'user_id' => $author->id,
        'commentable_type' => Track::class,
        'commentable_id' => $track->id,
        'body' => 'Mine',
    ]);

    $this->actingAs($intruder, 'sanctum')->deleteJson("/api/comments/{$comment->id}")->assertForbidden();
    $this->actingAs($author, 'sanctum')->deleteJson("/api/comments/{$comment->id}")->assertOk();

    expect(Comment::find($comment->id))->toBeNull();
});

test('commenting on a track notifies the artist\'s owning user, but not the commenter themselves', function () {
    Notification::fake();

    $artistOwner = User::factory()->create();
    $artist = Artist::factory()->create(['user_id' => $artistOwner->id]);
    $track = Track::factory()->create(['artist_id' => $artist->id]);
    $commenter = User::factory()->create();

    $this->actingAs($commenter, 'sanctum')->postJson("/api/tracks/{$track->id}/comments", ['body' => 'Nice!'])->assertCreated();

    Notification::assertSentTo($artistOwner, NewCommentNotification::class);

    // The artist commenting on their own track should not self-notify.
    $this->actingAs($artistOwner, 'sanctum')->postJson("/api/tracks/{$track->id}/comments", ['body' => 'Thanks!'])->assertCreated();
    Notification::assertSentToTimes($artistOwner, NewCommentNotification::class, 1);
});

test('commenting on a track owned by an unclaimed artist does not error', function () {
    $track = Track::factory()->create(); // ArtistFactory leaves user_id null by default
    $commenter = User::factory()->create();

    $this->actingAs($commenter, 'sanctum')
        ->postJson("/api/tracks/{$track->id}/comments", ['body' => 'Still works!'])
        ->assertCreated();
});
