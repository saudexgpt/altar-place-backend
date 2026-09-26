<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-wide maintenance mode, distinct from Laravel's own `artisan
 * down` (which would lock out staff along with everyone else). Staff
 * (moderator/super-admin) always pass through, via their existing session
 * or token, so they can sign in and switch it back off; every other
 * caller — web and mobile alike, since this sits on the shared `api`
 * group — gets a 503 until then.
 */
class BlockDuringMaintenance
{
    /**
     * @var list<string>
     */
    private const ALWAYS_ALLOWED = ['api/platform/status', 'api/auth/login', 'api/auth/logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformSetting::get('maintenance_mode', false)) {
            return $next($request);
        }

        if ($request->is(...self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        $user = $request->user('sanctum');

        if ($user && ($user->hasRole('moderator') || $user->hasRole('super-admin'))) {
            return $next($request);
        }

        return response()->json([
            'message' => 'The platform is temporarily down for maintenance.',
            'maintenance_message' => PlatformSetting::get('maintenance_message'),
        ], 503);
    }
}
