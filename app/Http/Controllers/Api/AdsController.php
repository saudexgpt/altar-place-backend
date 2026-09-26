<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServedAdResource;
use App\Models\Advertisement;
use App\Services\Ads\AdSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Listener-facing ad serving: guest-accessible like catalog/discovery
 * (an optional Bearer token, resolved via the `sanctum` guard directly
 * rather than the `auth:sanctum` middleware, personalizes targeting when
 * present but is never required).
 */
class AdsController extends Controller
{
    public function __construct(private AdSelector $selector) {}

    public function serve(Request $request): JsonResponse
    {
        $request->validate([
            'placement' => ['required', Rule::in(['banner', 'interstitial', 'audio', 'sponsored_playlist', 'sponsored_artist'])],
            'device_type' => ['nullable', 'string', 'max:50'],
        ]);

        $ad = $this->selector->select(
            $request->string('placement')->toString(),
            $request->user('sanctum'),
            $request->input('device_type'),
        );

        return response()->json(['ad' => $ad ? new ServedAdResource($ad) : null]);
    }

    public function impression(Request $request, Advertisement $advertisement): JsonResponse
    {
        $advertisement->impressions()->create(['user_id' => $request->user('sanctum')?->id]);
        $advertisement->increment('impressions_count');

        return response()->json(['message' => 'Recorded.'], 201);
    }

    public function click(Request $request, Advertisement $advertisement): JsonResponse
    {
        $advertisement->clicks()->create(['user_id' => $request->user('sanctum')?->id]);
        $advertisement->increment('clicks_count');

        return response()->json(['message' => 'Recorded.'], 201);
    }
}
