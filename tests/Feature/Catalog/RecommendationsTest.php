<?php

use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Genre;
use App\Models\PlayHistory;
use App\Models\SearchHistory;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Carbon;

function playTrack(User $user, Track $track, bool $completed = false): PlayHistory
{
    return PlayHistory::create([
        'user_id' => $user->id,
        'track_id' => $track->id,
        'played_at' => now(),
        'completed' => $completed,
    ]);
}

test('recommended falls back to trending for a guest', function () {
    $popular = Track::factory()->create(['plays_count' => 500]);
    Track::factory()->create(['plays_count' => 10]);

    $response = $this->getJson('/api/discovery/recommended');

    $response->assertOk();
    expect($response->json('data.0.id'))->toBe($popular->id);
});

test('recommended favors genres the user has played or favorited', function () {
    $lovedGenre = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);
    $otherGenre = Genre::create(['name' => 'Jazz', 'slug' => 'jazz']);

    $user = User::factory()->create();
    $lovedTrack = Track::factory()->create(['genre_id' => $lovedGenre->id, 'plays_count' => 1]);
    playTrack($user, $lovedTrack, completed: true);

    $matching = Track::factory()->create(['genre_id' => $lovedGenre->id, 'plays_count' => 50]);
    $unrelated = Track::factory()->create(['genre_id' => $otherGenre->id, 'plays_count' => 9999]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/discovery/recommended');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($matching->id);
    expect($ids)->not->toContain($unrelated->id);
});

test('following an artist influences recommendations even without listening history', function () {
    $genre = Genre::create(['name' => 'Worship', 'slug' => 'worship']);
    $artist = Artist::factory()->create();
    $followedArtistTrack = Track::factory()->create(['artist_id' => $artist->id, 'genre_id' => $genre->id, 'plays_count' => 5]);

    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/discovery/recommended');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->toContain($followedArtistTrack->id);
});

test('daily mix requires authentication', function () {
    $this->getJson('/api/discovery/daily-mix')->assertUnauthorized();
});

test('daily mix is stable across requests on the same day', function () {
    $genre = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);
    $user = User::factory()->create();
    $track = Track::factory()->create(['genre_id' => $genre->id]);
    playTrack($user, $track);
    Track::factory()->count(10)->create(['genre_id' => $genre->id]);

    $first = $this->actingAs($user, 'sanctum')->getJson('/api/discovery/daily-mix');
    $second = $this->actingAs($user, 'sanctum')->getJson('/api/discovery/daily-mix');

    $first->assertOk();
    expect($first->json('data'))->toEqual($second->json('data'));
});

test('discover weekly requires authentication', function () {
    $this->getJson('/api/discovery/discover-weekly')->assertUnauthorized();
});

test('discover weekly excludes tracks the user has already played or favorited', function () {
    $genre = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);
    $user = User::factory()->create();

    $alreadyPlayed = Track::factory()->create(['genre_id' => $genre->id]);
    playTrack($user, $alreadyPlayed);

    $alreadyFavorited = Track::factory()->create(['genre_id' => $genre->id]);
    Favorite::create(['user_id' => $user->id, 'favoritable_type' => Track::class, 'favoritable_id' => $alreadyFavorited->id]);

    $newToMe = Track::factory()->create(['genre_id' => $genre->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/discovery/discover-weekly');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($newToMe->id);
    expect($ids)->not->toContain($alreadyPlayed->id);
    expect($ids)->not->toContain($alreadyFavorited->id);
});

test('recommended podcasts only returns podcast-type tracks', function () {
    $podcast = Track::factory()->create(['type' => 'podcast', 'plays_count' => 20]);
    Track::factory()->create(['type' => 'music', 'plays_count' => 9999]);

    $response = $this->getJson('/api/discovery/recommended-podcasts');

    $response->assertOk();
    $types = collect($response->json('data'))->pluck('type');
    expect($types->unique()->all())->toBe(['podcast']);
    expect(collect($response->json('data'))->pluck('id'))->toContain($podcast->id);
});

test('similar artists returns other artists sharing a genre, excluding the artist itself', function () {
    $genre = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);
    $artist = Artist::factory()->create();
    Track::factory()->create(['artist_id' => $artist->id, 'genre_id' => $genre->id]);

    $similar = Artist::factory()->create();
    Track::factory()->create(['artist_id' => $similar->id, 'genre_id' => $genre->id]);

    $unrelated = Artist::factory()->create();
    $otherGenre = Genre::create(['name' => 'Jazz', 'slug' => 'jazz']);
    Track::factory()->create(['artist_id' => $unrelated->id, 'genre_id' => $otherGenre->id]);

    $response = $this->getJson("/api/artists/{$artist->id}/similar");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($similar->id);
    expect($ids)->not->toContain($artist->id);
    expect($ids)->not->toContain($unrelated->id);
});

test('co-listening boosts an artist\'s similarity ranking even across different genres', function () {
    $genreA = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);
    $genreB = Genre::create(['name' => 'Afrobeat', 'slug' => 'afrobeat']);

    $artist = Artist::factory()->create();
    $mainTrack = Track::factory()->create(['artist_id' => $artist->id, 'genre_id' => $genreA->id]);

    $coListenedArtist = Artist::factory()->create();
    $coListenedTrack = Track::factory()->create(['artist_id' => $coListenedArtist->id, 'genre_id' => $genreB->id]);

    $listener = User::factory()->create();
    playTrack($listener, $mainTrack);
    playTrack($listener, $coListenedTrack);

    $response = $this->getJson("/api/artists/{$artist->id}/similar");

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->toContain($coListenedArtist->id);
});

test('marking a track complete flags the most recent play history row', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create();
    playTrack($user, $track);

    $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/complete")->assertOk();

    expect(PlayHistory::where('user_id', $user->id)->where('track_id', $track->id)->first()->completed)->toBeTrue();
});

test('completed plays are weighted more heavily than started-only plays in recommendations', function () {
    Carbon::setTestNow('2026-01-01 12:00:00');

    $completedGenre = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);
    $startedGenre = Genre::create(['name' => 'Jazz', 'slug' => 'jazz']);

    $user = User::factory()->create();

    $completedTrack = Track::factory()->create(['genre_id' => $completedGenre->id]);
    playTrack($user, $completedTrack, completed: true);

    $skippedTrack = Track::factory()->create(['genre_id' => $startedGenre->id]);
    playTrack($user, $skippedTrack, completed: false);

    $completedGenreCandidate = Track::factory()->create(['genre_id' => $completedGenre->id, 'plays_count' => 1]);
    $startedGenreCandidate = Track::factory()->create(['genre_id' => $startedGenre->id, 'plays_count' => 1]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/discovery/recommended');
    $ids = collect($response->json('data'))->pluck('id')->values();

    expect($ids->search($completedGenreCandidate->id))->toBeLessThan($ids->search($startedGenreCandidate->id));

    Carbon::setTestNow();
});

test('searching while authenticated logs search history', function () {
    Track::factory()->create(['title' => 'Amazing Grace']);
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/search?q=grace')->assertOk();

    expect(SearchHistory::where('user_id', $user->id)->where('query', 'grace')->exists())->toBeTrue();
});

test('guest searches are not logged', function () {
    Track::factory()->create(['title' => 'Amazing Grace']);

    $this->getJson('/api/search?q=grace')->assertOk();

    expect(SearchHistory::where('query', 'grace')->exists())->toBeFalse();
});
