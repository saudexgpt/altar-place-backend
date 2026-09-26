<?php

use App\Models\Advertiser;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Carbon;

function makeAdvertiser(): Advertiser
{
    $user = User::factory()->create();

    return Advertiser::create([
        'user_id' => $user->id,
        'company_name' => 'Test Co',
        'is_verified' => true,
    ]);
}

test('serves an active campaign for a matching placement', function () {
    $advertiser = makeAdvertiser();
    $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Hello there',
    ]);

    $response = $this->getJson('/api/ads/serve?placement=banner');

    $response->assertOk();
    expect($response->json('ad.headline'))->toBe('Hello there');
});

test('does not serve a paused or draft campaign', function () {
    $advertiser = makeAdvertiser();
    $advertiser->advertisements()->create(['type' => 'banner', 'status' => 'paused', 'headline' => 'Paused']);
    $advertiser->advertisements()->create(['type' => 'banner', 'status' => 'draft', 'headline' => 'Draft']);

    $response = $this->getJson('/api/ads/serve?placement=banner');

    $response->assertOk();
    expect($response->json('ad'))->toBeNull();
});

test('does not serve a campaign outside its scheduled window', function () {
    $advertiser = makeAdvertiser();
    $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Not yet',
        'starts_at' => now()->addDay(),
    ]);

    $response = $this->getJson('/api/ads/serve?placement=banner');

    expect($response->json('ad'))->toBeNull();
});

test('country/state/city targeting only serves to matching users', function () {
    $advertiser = makeAdvertiser();
    $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Local deal',
        'targeting' => ['countries' => ['Nigeria'], 'states' => ['Lagos']],
    ]);

    $matchingUser = User::factory()->create(['country' => 'Nigeria', 'state' => 'Lagos']);
    $otherUser = User::factory()->create(['country' => 'Ghana', 'state' => 'Accra']);

    $this->actingAs($matchingUser, 'sanctum')
        ->getJson('/api/ads/serve?placement=banner')
        ->assertOk()
        ->assertJsonPath('ad.headline', 'Local deal');

    $this->actingAs($otherUser, 'sanctum')
        ->getJson('/api/ads/serve?placement=banner')
        ->assertOk()
        ->assertJsonPath('ad', null);
});

test('device type targeting excludes non-matching devices', function () {
    $advertiser = makeAdvertiser();
    $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Android only',
        'targeting' => ['device_types' => ['android']],
    ]);

    $this->getJson('/api/ads/serve?placement=banner&device_type=ios')
        ->assertJsonPath('ad', null);

    $this->getJson('/api/ads/serve?placement=banner&device_type=android')
        ->assertJsonPath('ad.headline', 'Android only');
});

test('genre targeting matches the listener\'s most-played genre', function () {
    $genre = Genre::create(['name' => 'Gospel', 'slug' => 'gospel']);
    $otherGenre = Genre::create(['name' => 'Jazz', 'slug' => 'jazz']);

    $advertiser = makeAdvertiser();
    $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Gospel fans',
        'targeting' => ['genre_ids' => [$genre->id]],
    ]);

    $user = User::factory()->create();
    $artist = Artist::factory()->create();
    $gospelTrack = Track::factory()->create(['genre_id' => $genre->id, 'artist_id' => $artist->id]);
    $jazzTrack = Track::factory()->create(['genre_id' => $otherGenre->id, 'artist_id' => $artist->id]);

    // User plays the gospel track twice, the jazz track once, so gospel wins.
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $gospelTrack->id, 'played_at' => now()]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $gospelTrack->id, 'played_at' => now()]);
    PlayHistory::create(['user_id' => $user->id, 'track_id' => $jazzTrack->id, 'played_at' => now()]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/ads/serve?placement=banner')
        ->assertJsonPath('ad.headline', 'Gospel fans');

    $userWithNoHistory = User::factory()->create();
    $this->actingAs($userWithNoHistory, 'sanctum')
        ->getJson('/api/ads/serve?placement=banner')
        ->assertJsonPath('ad', null);
});

test('age range targeting requires a known date of birth within range', function () {
    Carbon::setTestNow('2026-01-01');

    $advertiser = makeAdvertiser();
    $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Adults only',
        'targeting' => ['age_min' => 18, 'age_max' => 40],
    ]);

    $adult = User::factory()->create(['date_of_birth' => '2000-01-01']); // 26
    $teen = User::factory()->create(['date_of_birth' => '2015-01-01']); // 11
    $unknownAge = User::factory()->create(['date_of_birth' => null]);

    $this->actingAs($adult, 'sanctum')->getJson('/api/ads/serve?placement=banner')
        ->assertJsonPath('ad.headline', 'Adults only');

    $this->actingAs($teen, 'sanctum')->getJson('/api/ads/serve?placement=banner')
        ->assertJsonPath('ad', null);

    $this->actingAs($unknownAge, 'sanctum')->getJson('/api/ads/serve?placement=banner')
        ->assertJsonPath('ad', null);

    Carbon::setTestNow();
});

test('stops serving once the daily impression cap is reached', function () {
    $advertiser = makeAdvertiser();
    $advertisement = $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Limited run',
        'daily_impression_cap' => 2,
    ]);

    $advertisement->impressions()->create([]);
    $advertisement->impressions()->create([]);

    $this->getJson('/api/ads/serve?placement=banner')->assertJsonPath('ad', null);
});

test('sponsored playlist campaigns resolve the actual playlist in the response', function () {
    $advertiser = makeAdvertiser();
    $playlist = Playlist::factory()->create(['title' => 'Worship Essentials']);

    $advertiser->advertisements()->create([
        'type' => 'sponsored_playlist',
        'status' => 'active',
        'headline' => 'Featured Playlist',
        'sponsorable_type' => Playlist::class,
        'sponsorable_id' => $playlist->id,
    ]);

    $response = $this->getJson('/api/ads/serve?placement=sponsored_playlist');

    $response->assertOk();
    expect($response->json('ad.sponsorable.title'))->toBe('Worship Essentials');
});

test('impression and click endpoints record events and increment counters, even for guests', function () {
    $advertiser = makeAdvertiser();
    $advertisement = $advertiser->advertisements()->create([
        'type' => 'banner',
        'status' => 'active',
        'headline' => 'Track me',
    ]);

    $this->postJson("/api/ads/{$advertisement->id}/impression")->assertCreated();
    $this->postJson("/api/ads/{$advertisement->id}/click")->assertCreated();

    $advertisement->refresh();
    expect($advertisement->impressions_count)->toBe(1);
    expect($advertisement->clicks_count)->toBe(1);
    expect($advertisement->impressions()->count())->toBe(1);
    expect($advertisement->clicks()->count())->toBe(1);
});
