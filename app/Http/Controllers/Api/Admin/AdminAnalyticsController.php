<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTrackResource;
use App\Models\Favorite;
use App\Models\Payment;
use App\Models\PlayHistory;
use App\Models\Playlist;
use App\Models\Subscription;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminAnalyticsController extends Controller
{
    /**
     * Business analytics overview. DAU/MAU come from `users.last_active_at`
     * (stamped by TrackUserActivity middleware on every authenticated
     * request), so only "active today" / "active in the last 30 days"
     * snapshots are available here — not a historical day-by-day trend
     * (see chart() for that). Revenue/conversion/churn are derived from the
     * existing Payment/Subscription models.
     */
    public function overview(): JsonResponse
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $totalUsers = User::count();
        $activeSubscriptions = Subscription::currentlyActive()->count();
        $churned = Subscription::whereIn('status', ['canceled', 'expired'])
            ->where('canceled_at', '>=', $thirtyDaysAgo)
            ->count();
        $churnBase = $activeSubscriptions + $churned;

        return response()->json([
            'dau' => User::where('last_active_at', '>=', $todayStart)->count(),
            'mau' => User::where('last_active_at', '>=', $thirtyDaysAgo)->count(),
            'dau_mau_note' => 'Snapshot counts from users\' last_active_at, not a historical daily trend.',

            'total_users' => $totalUsers,
            'total_creators' => User::role('creator')->count(),
            'total_advertisers' => User::role('advertiser')->count(),
            'total_tracks' => Track::count(),
            'music_tracks_count' => Track::ofType('music')->count(),
            'word_tracks_count' => Track::whereIn('type', ['sermon', 'podcast'])->count(),
            'total_plays' => (int) Track::sum('plays_count'),

            'revenue' => [
                'total' => (int) Payment::where('status', 'successful')->sum('amount'),
                'last_30_days' => (int) Payment::where('status', 'successful')->where('paid_at', '>=', $thirtyDaysAgo)->sum('amount'),
                'note' => 'Summed in the smallest currency unit as stored on payments (kobo/cents).',
            ],

            'active_subscriptions' => $activeSubscriptions,
            'conversion_rate' => $totalUsers > 0 ? round($activeSubscriptions / $totalUsers * 100, 2) : 0.0,

            'churn_rate' => $churnBase > 0 ? round($churned / $churnBase * 100, 2) : 0.0,
            'churn_note' => '30-day approximation: subscriptions canceled/expired in the last 30 days vs. currently-active + churned.',
        ]);
    }

    /**
     * Daily "Music Plays" vs "Word Plays" (sermons + podcasts) for the
     * admin dashboard's trend chart, built from PlayHistory rows joined to
     * tracks — a real day-by-day series, unlike the DAU/MAU snapshot above.
     */
    public function chart(Request $request): JsonResponse
    {
        $days = max(1, min(90, $request->integer('days', 7)));
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = PlayHistory::query()
            ->join('tracks', 'tracks.id', '=', 'play_histories.track_id')
            ->where('play_histories.played_at', '>=', $start)
            ->selectRaw('DATE(play_histories.played_at) as day, tracks.type as type, COUNT(*) as plays')
            ->groupBy('day', 'type')
            ->get();

        $labels = [];
        $musicPlays = [];
        $wordPlays = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('M j');

            $dayRows = $rows->where('day', $key);
            $musicPlays[] = (int) $dayRows->where('type', 'music')->sum('plays');
            $wordPlays[] = (int) $dayRows->whereIn('type', ['sermon', 'podcast'])->sum('plays');
        }

        return response()->json([
            'labels' => $labels,
            'music_plays' => $musicPlays,
            'word_plays' => $wordPlays,
        ]);
    }

    /**
     * Top content for the dashboard's ranked list. "Most Liked" is a real,
     * distinct ranking (favorites count); this app doesn't track a separate
     * "view" event for a track, so "Most Viewed" intentionally reuses the
     * "Most Played" ranking rather than inventing an untracked metric.
     *
     * An optional `days` window re-ranks by activity within that period
     * (matching the dashboard's date-range picker) instead of the all-time
     * totals on the tracks table.
     */
    public function topContent(Request $request): AnonymousResourceCollection
    {
        $metric = $request->string('metric', 'played')->toString();
        $limit = max(1, min(20, $request->integer('limit', 5)));
        $days = $request->integer('days');

        if ($days) {
            return AdminTrackResource::collection($this->topContentForWindow($metric, $limit, $days));
        }

        $query = Track::query()->with('artist')->approved();

        if ($metric === 'liked') {
            $query->withCount('favorites')->orderByDesc('favorites_count');
        } else {
            $query->orderByDesc('plays_count');
        }

        return AdminTrackResource::collection($query->limit($limit)->get());
    }

    /**
     * @return \Illuminate\Support\Collection<int, Track>
     */
    private function topContentForWindow(string $metric, int $limit, int $days): \Illuminate\Support\Collection
    {
        $since = now()->subDays($days);

        if ($metric === 'liked') {
            $rankedIds = Favorite::where('favoritable_type', Track::class)
                ->where('created_at', '>=', $since)
                ->selectRaw('favoritable_id, COUNT(*) as aggregate')
                ->groupBy('favoritable_id')
                ->orderByDesc('aggregate')
                ->limit($limit)
                ->pluck('favoritable_id');
        } else {
            $rankedIds = PlayHistory::where('played_at', '>=', $since)
                ->selectRaw('track_id, COUNT(*) as aggregate')
                ->groupBy('track_id')
                ->orderByDesc('aggregate')
                ->limit($limit)
                ->pluck('track_id');
        }

        $tracks = Track::with('artist')->approved()->whereIn('id', $rankedIds)->get()->keyBy('id');

        return $rankedIds->map(fn ($id) => $tracks->get($id))->filter()->values();
    }

    /**
     * A synthesized "recent activity" feed for the dashboard — merged from
     * the most recently created/updated rows across users, tracks, and
     * playlists, normalized into one shape and sorted by recency. This is
     * real data from existing timestamps, not a dedicated persisted
     * activity-log table (a bigger, separate feature not built here).
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $limit = max(1, min(50, $request->integer('limit', 8)));

        $users = User::latest()->limit($limit)->get()->map(fn (User $user) => [
            'type' => 'user_registered',
            'title' => 'New user registered',
            'subtitle' => $user->email,
            'at' => $user->created_at,
        ]);

        $newTracks = Track::with('artist')->latest()->limit($limit)->get()->map(fn (Track $track) => [
            'type' => $track->type === 'music' ? 'song_uploaded' : 'sermon_added',
            'title' => $track->type === 'music' ? 'New song uploaded' : 'New sermon added',
            'subtitle' => $track->title,
            'at' => $track->created_at,
        ]);

        $updatedTracks = Track::with('artist')
            ->whereColumn('updated_at', '>', 'created_at')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Track $track) => [
                'type' => 'song_updated',
                'title' => $track->type === 'music' ? 'Song updated' : 'Sermon updated',
                'subtitle' => $track->title,
                'at' => $track->updated_at,
            ]);

        $playlists = Playlist::latest('updated_at')->limit($limit)->get()->map(fn (Playlist $playlist) => [
            'type' => 'playlist_updated',
            'title' => $playlist->wasRecentlyCreated ?? false ? 'Playlist created' : 'Playlist updated',
            'subtitle' => $playlist->title,
            'at' => $playlist->updated_at,
        ]);

        $activity = $users->concat($newTracks)->concat($updatedTracks)->concat($playlists)
            ->sortByDesc(fn ($item) => $item['at'])
            ->take($limit)
            ->map(fn ($item) => [
                ...$item,
                'at' => $item['at']->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $activity]);
    }
}
