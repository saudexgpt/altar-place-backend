<?php

use Illuminate\Support\Facades\Cache;

function cachedRedirectUriFor(string $targetUrl): ?string
{
    parse_str((string) parse_url($targetUrl, PHP_URL_QUERY), $query);

    return Cache::get('oauth_redirect:'.$query['state']);
}

test('a redirect_uri matching the live app domain is honored even when FRONTEND_URL is unset', function () {
    config(['app.url' => 'https://altarplace.com', 'app.frontend_url' => 'http://localhost:8100']);

    $response = $this->getJson('/api/auth/social/google/redirect?'.http_build_query([
        'redirect_uri' => 'https://altarplace.com/oauth-callback',
    ]));

    $response->assertOk();
    expect(cachedRedirectUriFor($response->json('url')))->toBe('https://altarplace.com/oauth-callback');
});

test('a redirect_uri matching a configured FRONTEND_URL is also honored', function () {
    config(['app.url' => 'https://altarplace.com', 'app.frontend_url' => 'https://mobile.altarplace.com']);

    $response = $this->getJson('/api/auth/social/google/redirect?'.http_build_query([
        'redirect_uri' => 'https://mobile.altarplace.com/oauth-callback',
    ]));

    $response->assertOk();
    expect(cachedRedirectUriFor($response->json('url')))->toBe('https://mobile.altarplace.com/oauth-callback');
});

test('a redirect_uri pointing at a foreign host is rejected and falls back to the app domain', function () {
    config(['app.url' => 'https://altarplace.com', 'app.frontend_url' => 'http://localhost:8100']);

    $response = $this->getJson('/api/auth/social/google/redirect?'.http_build_query([
        'redirect_uri' => 'https://evil.example.com/steal-token',
    ]));

    $response->assertOk();
    expect(cachedRedirectUriFor($response->json('url')))->toBe('https://altarplace.com/oauth-callback');
});

test('omitting redirect_uri falls back to the app domain, not FRONTEND_URL default', function () {
    config(['app.url' => 'https://altarplace.com', 'app.frontend_url' => 'http://localhost:8100']);

    $response = $this->getJson('/api/auth/social/google/redirect');

    $response->assertOk();
    expect(cachedRedirectUriFor($response->json('url')))->toBe('https://altarplace.com/oauth-callback');
});
