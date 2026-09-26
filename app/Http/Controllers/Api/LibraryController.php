<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\TrackResource;
use App\Models\Activity;
use App\Models\DownloadEvent;
use App\Models\Track;
use App\Models\User;
use App\Services\Community\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LibraryController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function favorites(Request $request): AnonymousResourceCollection
    {
        $trackIds = $request->user()->favorites()
            ->where('favoritable_type', Track::class)
            ->latest()
            ->pluck('favoritable_id');

        $tracks = Track::with(['artist', 'album', 'genre'])
            ->whereIn('id', $trackIds)
            ->get()
            ->sortBy(fn (Track $track) => array_search($track->id, $trackIds->all()))
            ->values();

        return TrackResource::collection($tracks);
    }

    public function favoriteTrack(Request $request, Track $track): JsonResponse
    {
        $user = $request->user();

        $favorite = $user->favorites()->firstOrCreate([
            'favoritable_type' => Track::class,
            'favoritable_id' => $track->id,
        ]);

        if ($favorite->wasRecentlyCreated) {
            $this->activity->log($user, Activity::TYPE_FAVORITED_TRACK, $track);
        }

        return response()->json(['message' => 'Track added to favorites.']);
    }

    public function unfavoriteTrack(Request $request, Track $track): JsonResponse
    {
        $request->user()->favorites()
            ->where('favoritable_type', Track::class)
            ->where('favoritable_id', $track->id)
            ->delete();

        return response()->json(['message' => 'Track removed from favorites.']);
    }

    public function shareTrack(Request $request, Track $track): JsonResponse
    {
        $request->validate(['platform' => ['nullable', 'string', 'max:50']]);

        $user = $request->user();

        $track->shares()->create([
            'user_id' => $user->id,
            'platform' => $request->input('platform'),
        ]);

        $this->activity->log($user, Activity::TYPE_SHARED_TRACK, $track);

        return response()->json(['message' => 'Share logged.'], 201);
    }

    /**
     * The client calls this when a track reaches its natural end (not when
     * skipped away from early) — the recommendation engine weights
     * completed plays more heavily than started-only ones as a taste signal.
     */
    public function markTrackComplete(Request $request, Track $track): JsonResponse
    {
        $request->user()->playHistories()
            ->where('track_id', $track->id)
            ->latest('played_at')
            ->first()
            ?->update(['completed' => true]);

        return response()->json(['message' => 'Marked complete.']);
    }

    public function recentlyPlayed(Request $request): AnonymousResourceCollection
    {
        $trackIds = $request->user()->playHistories()
            ->latest('played_at')
            ->limit(50)
            ->pluck('track_id')
            ->unique()
            ->values();

        $tracks = Track::with(['artist', 'album', 'genre'])
            ->whereIn('id', $trackIds)
            ->get()
            ->sortBy(fn (Track $track) => array_search($track->id, $trackIds->all()))
            ->values();

        return TrackResource::collection($tracks);
    }

    public function playlists(Request $request): AnonymousResourceCollection
    {
        return PlaylistResource::collection(
            $request->user()->playlists()->withCount('tracks')->latest()->get()
        );
    }

    /**
     * Logs that the authenticated user downloaded a track for offline
     * playback (the actual file caching happens client-side via Capacitor
     * Filesystem). Feeds the creator analytics "Downloads" metric and is
     * gated by the user's plan (Free tier has a monthly download limit).
     */
    public function logDownload(Request $request, Track $track): JsonResponse
    {
        $user = $request->user();
        $limit = $user->currentPlan()->downloadLimit();

        if ($limit !== null && $this->downloadsThisPeriod($user) >= $limit) {
            return response()->json([
                'message' => "You've reached your plan's limit of {$limit} downloads this month. Upgrade to Premium for unlimited downloads.",
            ], 403);
        }

        DownloadEvent::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
        ]);

        return response()->json(['message' => 'Download logged.'], 201);
    }

    public function downloadQuota(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $user->currentPlan()->downloadLimit();
        $used = $this->downloadsThisPeriod($user);

        return response()->json([
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'unlimited' => $limit === null,
        ]);
    }

    private function downloadsThisPeriod(User $user): int
    {
        return DownloadEvent::where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}
