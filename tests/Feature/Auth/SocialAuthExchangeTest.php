<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('a valid bridge token establishes a session and is consumed', function () {
    $user = User::factory()->create();
    $token = $user->createToken('oauth-google')->plainTextToken;

    $response = $this->postJson('/api/auth/social/exchange', ['token' => $token]);

    $response->assertOk();
    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
    expect(PersonalAccessToken::findToken($token))->toBeNull();
});

test('an invalid bridge token is rejected', function () {
    $response = $this->postJson('/api/auth/social/exchange', ['token' => 'not-a-real-token']);

    $response->assertUnauthorized();
});

test('a bridge token cannot be reused', function () {
    $user = User::factory()->create();
    $token = $user->createToken('oauth-google')->plainTextToken;

    $this->postJson('/api/auth/social/exchange', ['token' => $token])->assertOk();
    $this->postJson('/api/auth/social/exchange', ['token' => $token])->assertUnauthorized();
});
