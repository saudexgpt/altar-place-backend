<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Picks the right base URL for a link sent back to the user (password
 * reset, email verification, payment checkout callback), based on where
 * the triggering request actually came from — the web app is served by
 * this very Laravel app, so its own APP_URL is the right target; the
 * mobile app has no such thing and keeps using FRONTEND_URL. Getting this
 * wrong means a web user's reset-password email links to the mobile app.
 */
class FrontendUrl
{
    public static function resolve(?Request $request = null): string
    {
        $request ??= request();

        $isWeb = $request && EnsureFrontendRequestsAreStateful::fromFrontend($request);

        return rtrim((string) config($isWeb ? 'app.url' : 'app.frontend_url'), '/');
    }
}
