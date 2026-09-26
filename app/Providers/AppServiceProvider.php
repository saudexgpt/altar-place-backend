<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // If the app is configured to live at an https:// URL, every URL it
        // generates (Vite assets, signed links, redirects) must be https —
        // even when the request reaches PHP as plain HTTP behind a TLS-
        // terminating proxy. Otherwise browsers block the page's own assets
        // as mixed content.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
