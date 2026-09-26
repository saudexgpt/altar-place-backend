<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreatorApplyRequest;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\UserResource;
use App\Models\Artist;
use App\Models\DownloadEvent;
use App\Models\PlayHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreatorController extends Controller
{
    public function apply(CreatorApplyRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->artist) {
            return response()->json(['message' => 'You already have a creator profile.'], 422);
        }

        $artist = Artist::create([
            'user_id' => $user->id,
            'name' => $request->string('artist_name'),
            'slug' => Str::slug($request->string('artist_name')).'-'.Str::random(6),
            'bio' => $request->input('bio'),
            'is_verified' => false,
        ]);

        $user->assignRole('creator');

        return response()->json([
            'artist' => new ArtistResource($artist),
            'user' => new UserResource($user->fresh()),
        ], 201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $artist = $request->user()->artist()->withCount(['tracks', 'albums', 'followers'])->firstOrFail();

        return response()->json([
            'artist' => new ArtistResource($artist),
            'total_tracks' => $artist->tracks_count,
            'total_albums' => $artist->albums_count,
            'total_followers' => $artist->followers_count,
            'total_streams' => (int) $artist->tracks()->sum('plays_count'),
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $artist = $request->user()->artist()->firstOrFail();
        $trackIds = $artist->tracks()->pluck('id');

        $uniqueListeners = PlayHistory::whereIn('track_id', $trackIds)->distinct('user_id')->count('user_id');
        $downloads = DownloadEvent::whereIn('track_id', $trackIds)->count();

        // Approximate total listening minutes: each logged play is assumed to
        // run the track's full duration. A precise "seconds actually
        // listened" figure would need client-side progress heartbeats,
        // which is beyond this phase's scope.
        $estimatedListeningMinutes = (int) round(
            $artist->tracks()
                ->selectRaw('SUM(plays_count * duration_seconds) as total_seconds')
                ->value('total_seconds') / 60
        );

        $perTrack = $artist->tracks()
            ->withCount('downloadEvents')
            ->get(['id', 'title', 'plays_count', 'duration_seconds'])
            ->map(fn ($track) => [
                'id' => $track->id,
                'title' => $track->title,
                'streams' => $track->plays_count,
                'downloads' => $track->download_events_count,
            ]);

        return response()->json([
            'streams' => (int) $artist->tracks()->sum('plays_count'),
            'unique_listeners' => $uniqueListeners,
            'estimated_listening_minutes' => $estimatedListeningMinutes,
            'followers' => $artist->followers()->count(),
            'downloads' => $downloads,
            'revenue' => null,
            'revenue_note' => 'Revenue tracking ships with Phase 3 monetization.',
            'tracks' => $perTrack,
        ]);
    }
}
