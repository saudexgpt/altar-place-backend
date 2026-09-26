<?php

use App\Models\Artist;
use App\Models\DownloadEvent;
use App\Models\SubscriptionPlan;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;

beforeEach(function () {
    $this->seed(SubscriptionPlanSeeder::class);
});

test('a free user sees their download quota', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/library/download-quota');

    $response->assertOk();
    expect($response->json('limit'))->toBe(10);
    expect($response->json('unlimited'))->toBeFalse();
});

test('a premium user has unlimited downloads', function () {
    $user = User::factory()->create();
    $plan = SubscriptionPlan::where('slug', 'premium-monthly')->first();
    $user->subscriptions()->create([
        'subscription_plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/library/download-quota');

    $response->assertOk();
    expect($response->json('unlimited'))->toBeTrue();
    expect($response->json('limit'))->toBeNull();
});

test('a free user is blocked once they hit the download limit', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        DownloadEvent::create(['user_id' => $user->id, 'track_id' => Track::factory()->for($artist)->create()->id]);
    }

    $track = Track::factory()->for($artist)->create();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/tracks/{$track->id}/download-event");

    $response->assertStatus(403);
});

test('downloads from a previous month do not count against this months quota', function () {
    $user = User::factory()->create();
    $artist = Artist::factory()->create();

    $oldEvent = DownloadEvent::create(['user_id' => $user->id, 'track_id' => Track::factory()->for($artist)->create()->id]);
    $oldEvent->forceFill(['created_at' => now()->subMonth()->startOfMonth()])->save();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/library/download-quota');

    expect($response->json('used'))->toBe(0);
});
