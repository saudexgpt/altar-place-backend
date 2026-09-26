<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SocialAuthController extends Controller
{
    /**
     * @var list<string>
     */
    private const SUPPORTED_PROVIDERS = ['google', 'facebook'];

    /**
     * Return the provider's OAuth consent URL. The frontend (web tab or the
     * system browser opened via Capacitor's Browser plugin on mobile) opens
     * this URL, and the provider redirects back to our callback below.
     */
    public function redirect(Request $request, string $provider): JsonResponse
    {
        $this->ensureProviderIsSupported($provider);

        $redirectUri = $request->query('redirect_uri', rtrim((string) config('app.frontend_url'), '/').'/oauth-callback');

        $state = Str::random(40);
        Cache::put("oauth_redirect:{$state}", $redirectUri, now()->addMinutes(10));

        $url = Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Handle the provider's OAuth callback: find or create the local user,
     * issue a Sanctum token, and hand control back to the frontend via the
     * redirect_uri captured in the redirect() step above.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsSupported($provider);

        $state = (string) $request->query('state');
        $redirectUri = Cache::pull("oauth_redirect:{$state}") ?? rtrim((string) config('app.frontend_url'), '/').'/oauth-callback';

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable) {
            return redirect()->away($redirectUri.'?error=oauth_failed');
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user) {
            $user = User::where('email', $socialUser->getEmail())->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'New User',
                'email' => $socialUser->getEmail(),
                'avatar_url' => $socialUser->getAvatar(),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $user->assignRole('listener');
        } elseif (! $user->provider) {
            $user->forceFill([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar_url' => $user->avatar_url ?? $socialUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        if (! $user->isActive()) {
            return redirect()->away($redirectUri.'?error=account_'.$user->status);
        }

        $token = $user->createToken('oauth-'.$provider)->plainTextToken;

        return redirect()->away($redirectUri.'?token='.$token.'&provider='.$provider);
    }

    /**
     * Exchanges the one-time bridge token dropped in the OAuth callback's
     * redirect URL for a Sanctum session cookie. Mobile just uses that token
     * directly (it's a normal personal access token); the web app never
     * stores it, since this request — made by our own frontend, not the
     * OAuth provider — is what EnsureFrontendRequestsAreStateful recognizes
     * as "from a stateful domain" and upgrades into a real session.
     */
    public function exchange(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $accessToken = PersonalAccessToken::findToken($request->string('token')->toString());

        abort_unless($accessToken, 401, 'Invalid or expired sign-in link.');

        $user = $accessToken->tokenable;
        $accessToken->delete();

        Auth::login($user);

        return response()->json(['user' => new UserResource($user)]);
    }

    private function ensureProviderIsSupported(string $provider): void
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new NotFoundHttpException("Unsupported OAuth provider [{$provider}].");
        }
    }
}
