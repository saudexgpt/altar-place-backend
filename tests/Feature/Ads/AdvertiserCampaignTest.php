<?php

use App\Models\Advertiser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a listener can apply to become an advertiser', function () {
    $user = User::factory()->create();
    $user->assignRole('listener');

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/advertiser/apply', [
        'company_name' => 'Acme Media',
        'website' => 'https://acme.example.com',
    ]);

    $response->assertCreated();
    expect($user->fresh()->hasRole('advertiser'))->toBeTrue();
    expect(Advertiser::where('user_id', $user->id)->exists())->toBeTrue();
});

test('applying twice is rejected', function () {
    $user = User::factory()->create();
    Advertiser::create(['user_id' => $user->id, 'company_name' => 'Existing Co']);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/advertiser/apply', [
        'company_name' => 'Another Co',
    ]);

    $response->assertStatus(422);
});

test('a non-advertiser cannot access advertiser-only routes', function () {
    $user = User::factory()->create();
    $user->assignRole('listener');

    $this->actingAs($user, 'sanctum')->getJson('/api/advertiser/dashboard')->assertForbidden();
});

test('an advertiser can create, update, and delete their own campaign', function () {
    $user = User::factory()->create();
    $user->assignRole('advertiser');
    $advertiser = Advertiser::create(['user_id' => $user->id, 'company_name' => 'Acme Media']);

    $create = $this->actingAs($user, 'sanctum')->postJson('/api/advertiser/campaigns', [
        'type' => 'banner',
        'headline' => 'Launch Sale',
        'cta_label' => 'Shop Now',
        'cta_url' => 'https://acme.example.com/sale',
    ]);

    $create->assertCreated();
    expect($create->json('advertisement.status'))->toBe('draft');
    $advertisementId = $create->json('advertisement.id');

    $update = $this->actingAs($user, 'sanctum')->putJson("/api/advertiser/campaigns/{$advertisementId}", [
        'status' => 'active',
    ]);
    $update->assertOk();
    expect($update->json('advertisement.status'))->toBe('active');

    $list = $this->actingAs($user, 'sanctum')->getJson('/api/advertiser/campaigns');
    $list->assertOk();
    expect($list->json())->toHaveCount(1);

    $delete = $this->actingAs($user, 'sanctum')->deleteJson("/api/advertiser/campaigns/{$advertisementId}");
    $delete->assertOk();
    expect($advertiser->advertisements()->count())->toBe(0);
});

test('an advertiser cannot modify another advertiser\'s campaign', function () {
    $owner = User::factory()->create();
    $owner->assignRole('advertiser');
    $ownerAdvertiser = Advertiser::create(['user_id' => $owner->id, 'company_name' => 'Owner Co']);
    $campaign = $ownerAdvertiser->advertisements()->create(['type' => 'banner', 'headline' => 'Mine']);

    $intruder = User::factory()->create();
    $intruder->assignRole('advertiser');
    Advertiser::create(['user_id' => $intruder->id, 'company_name' => 'Intruder Co']);

    $this->actingAs($intruder, 'sanctum')
        ->putJson("/api/advertiser/campaigns/{$campaign->id}", ['status' => 'active'])
        ->assertForbidden();

    $this->actingAs($intruder, 'sanctum')
        ->deleteJson("/api/advertiser/campaigns/{$campaign->id}")
        ->assertForbidden();
});

test('advertiser dashboard reports aggregate campaign stats', function () {
    $user = User::factory()->create();
    $user->assignRole('advertiser');
    $advertiser = Advertiser::create(['user_id' => $user->id, 'company_name' => 'Acme Media']);

    // impressions_count/clicks_count are intentionally not mass-assignable
    // (only ever advanced via AdsController::impression/click), so set them
    // directly here to simulate campaigns with existing traffic.
    $advertiser->advertisements()->create(['type' => 'banner', 'status' => 'active', 'headline' => 'A'])
        ->forceFill(['impressions_count' => 100, 'clicks_count' => 5])->save();
    $advertiser->advertisements()->create(['type' => 'banner', 'status' => 'paused', 'headline' => 'B'])
        ->forceFill(['impressions_count' => 50, 'clicks_count' => 1])->save();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/advertiser/dashboard');

    $response->assertOk();
    expect($response->json('total_campaigns'))->toBe(2);
    expect($response->json('active_campaigns'))->toBe(1);
    expect($response->json('total_impressions'))->toBe(150);
    expect($response->json('total_clicks'))->toBe(6);
});
