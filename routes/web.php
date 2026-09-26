<?php

use Illuminate\Support\Facades\Route;

// The Vue app (mounted in resources/views/app.blade.php) owns all
// client-side routing — this catch-all just serves that one shell for
// every non-API, non-asset path so Vue Router can take over.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|storage|images|build).*$');
