<?php

use App\Models\User;

test('an unauthenticated request to a protected endpoint gets a clean 401, not an HTML redirect', function () {
    $response = $this->getJson('/api/library/favorites');

    $response->assertUnauthorized();
    expect($response->headers->get('Content-Type'))->toContain('application/json');
    expect($response->json('message'))->not->toBeNull();
});

test('a stateful (web-origin) request with no session cookie still gets a clean 401', function () {
    $response = $this->withHeader('Origin', 'http://127.0.0.1:8000')
        ->getJson('/api/library/favorites');

    $response->assertUnauthorized();
});

test('a revoked mobile token can no longer authenticate — mirrors a session that expired mid-use', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mobile-device');
    $plainTextToken = $token->plainTextToken;

    $token->accessToken->delete();

    $response = $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->getJson('/api/library/favorites');

    $response->assertUnauthorized();
});
