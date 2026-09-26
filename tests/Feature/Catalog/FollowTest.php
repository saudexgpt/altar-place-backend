<?php

use App\Models\Artist;
use App\Models\User;

test('a user can follow and unfollow an artist', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();
    expect($user->follows()->where('followable_type', Artist::class)->count())->toBe(1);

    $this->actingAs($user, 'sanctum')->deleteJson("/api/artists/{$artist->id}/follow")->assertOk();
    expect($user->follows()->where('followable_type', Artist::class)->count())->toBe(0);
});

test('following an artist twice does not duplicate the follow record', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow")->assertOk();

    expect($user->follows()->where('followable_type', Artist::class)->count())->toBe(1);
});

test('followed artists appear in the profile following endpoint', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/artists/{$artist->id}/follow");

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile/following');

    $response->assertOk();
    expect($response->json('artists'))->toHaveCount(1);
    expect($response->json('artists.0.is_following'))->toBeTrue();
});
