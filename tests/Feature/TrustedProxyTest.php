<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

// App Platform terminates TLS in front of nginx, so the app only ever sees
// plain HTTP plus X-Forwarded-* headers. Without trusted proxies, generated
// URLs (notably the signed email-verification link) come out as http://.
test('the request scheme follows X-Forwarded-Proto from the load balancer', function () {
    Route::get('/api/__scheme', fn () => response()->json([
        'scheme' => request()->getScheme(),
        'url' => url('/x'),
    ]));

    $behindProxy = $this->withHeader('X-Forwarded-Proto', 'https')->getJson('/api/__scheme');

    $behindProxy->assertOk();
    expect($behindProxy->json('scheme'))->toBe('https');
    expect($behindProxy->json('url'))->toStartWith('https://');
});

test('a signed url generated behind the proxy is https and still validates', function () {
    Route::get('/api/__signed', fn () => response()->json(['ok' => request()->hasValidSignature()]))->name('test.signed');
    Route::get('/api/__make-signed', fn () => response()->json(['url' => URL::signedRoute('test.signed')]));
    // Routes added after boot aren't in the name lookup until refreshed.
    Route::getRoutes()->refreshNameLookups();

    $url = $this->withHeader('X-Forwarded-Proto', 'https')->getJson('/api/__make-signed')->json('url');

    expect($url)->toStartWith('https://');

    // Follow the link the way a browser would: same https scheme, via the proxy.
    $this->withHeader('X-Forwarded-Proto', 'https')
        ->getJson($url)
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('urls are forced to https when APP_URL is https, even with no forwarded headers', function () {
    config(['app.url' => 'https://altarplace.com']);
    (new AppServiceProvider($this->app))->boot();

    // A request that reaches PHP as plain http with no X-Forwarded-Proto —
    // the mixed-content case: asset() is what @vite uses for the CSS/JS URLs.
    expect(asset('build/assets/app.css'))->toStartWith('https://');
    expect(url('/x'))->toStartWith('https://');
});
