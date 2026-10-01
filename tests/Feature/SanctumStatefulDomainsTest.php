<?php

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

function requestFromOrigin(string $origin): Request
{
    $request = Request::create('/api/auth/register', 'POST');
    $request->headers->set('Origin', $origin);

    return $request;
}

test('capacitor android webview origin is misclassified as stateful when SANCTUM_STATEFUL_DOMAINS is unset', function () {
    config(['sanctum.stateful' => explode(',', 'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1')]);

    expect(EnsureFrontendRequestsAreStateful::fromFrontend(requestFromOrigin('https://localhost')))->toBeTrue();
});

test('capacitor android webview origin is treated as stateless once SANCTUM_STATEFUL_DOMAINS excludes localhost', function () {
    config(['sanctum.stateful' => explode(',', 'altarplace.com')]);

    expect(EnsureFrontendRequestsAreStateful::fromFrontend(requestFromOrigin('https://localhost')))->toBeFalse();
});

test('the real web domain still receives stateful cookie/CSRF handling once SANCTUM_STATEFUL_DOMAINS is scoped', function () {
    config(['sanctum.stateful' => explode(',', 'altarplace.com')]);

    expect(EnsureFrontendRequestsAreStateful::fromFrontend(requestFromOrigin('https://altarplace.com')))->toBeTrue();
});
