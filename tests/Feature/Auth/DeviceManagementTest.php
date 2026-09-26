<?php

use App\Models\User;

test('an authenticated user can list their active devices', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iphone-15')->plainTextToken;
    $user->createToken('chrome-web');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/devices');

    $response->assertOk()->assertJsonCount(2, 'devices');
});

test('an authenticated user can revoke another device', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iphone-15')->plainTextToken;
    $otherToken = $user->createToken('chrome-web');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/devices/{$otherToken->accessToken->id}");

    $response->assertOk();
    expect($user->tokens()->count())->toBe(1);
});

test('a user cannot revoke another users device', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('iphone-15')->plainTextToken;
    $otherToken = $otherUser->createToken('chrome-web');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/devices/{$otherToken->accessToken->id}");

    $response->assertNotFound();
    expect($otherUser->tokens()->count())->toBe(1);
});
