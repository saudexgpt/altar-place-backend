<?php

namespace App\Services\Recommendations;

use App\Models\Artist;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\PlayHistory;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A from-scratch heuristic recommendation engine — weighted genre affinity
 * (listening history, completion rate, likes, follows) plus a co-listening
 * signal for artist similarity. There's no ML model or external AI service
 * behind this; it's a transparent, explainable scoring system in the same
 * spirit as this codebase's other "smart" features (ad targeting, etc.).
 */
class RecommendationEngine
{
    private const FOR_YOU_SIZE = 20;

    private const DAILY_MIX_SIZE = 25;

    private const DISCOVER_WEEKLY_SIZE = 25;

    private const PODCAST_SIZE = 20;

    private const SIMILAR_ARTISTS_SIZE = 10;

    /**
     * A general-purpose "Recommended For You" list, optionally restricted to
     * a track type (music/podcast/sermon). Falls back to global trending
     * for guests or users with no signal yet.
     */
    public function forYou(?User $user, ?string $type = null): Collection
    {
        if (! $user) {
            return $this->trending($type, self::FOR_YOU_SIZE);
        }

        $genreScores = $this->genreAffinity($user);

        if ($genreScores->isEmpty()) {
            return $this->trending($type, self::FOR_YOU_SIZE);
        }

        return Cache::remember(
            "recs:for-you:{$user->id}:".($type ?? 'all'),
            now()->addHour(),
            function () use ($genreScores, $type) {
                return Track::query()
                    ->with(['artist', 'album', 'genre'])
                    ->approved()
                    ->when($type, fn ($q) => $q->ofType($type))
                    ->whereIn('genre_id', $genreScores->keys())
                    ->orderByDesc('plays_count')
                    ->limit(self::FOR_YOU_SIZE * 3)
                    ->get()
                    ->sortByDesc(fn (Track $track) => $genreScores->get($track->genre_id, 0) * 1000 + $track->plays_count)
                    ->take(self::FOR_YOU_SIZE)
                    ->values();
            }
        );
    }

    /**
     * A mix of familiar favorites and genre-matched tracks, stable for the
     * whole day (same list on every request, changes tomorrow).
     */
    public function dailyMix(User $user): Collection
    {
        $today = now()->toDateString();

        return Cache::remember("recs:daily-mix:{$user->id}:{$today}", now()->endOfDay(), function () use ($user, $today) {
            $genreScores = $this->genreAffinity($user);

            if ($genreScores->isEmpty()) {
                return $this->trending('music', self::DAILY_MIX_SIZE);
            }

            $candidates = Track::with(['artist', 'album', 'genre'])
                ->ofType('music')
                ->approved()
                ->whereIn('genre_id', $genreScores->keys())
                ->limit(200)
                ->get();

            return $this->seededShuffle($candidates, "daily:{$user->id}:{$today}")->take(self::DAILY_MIX_SIZE)->values();
        });
    }

    /**
     * Tracks that match the user's taste but that they haven't already
     * played or favorited — stable for the whole ISO week.
     */
    public function discoverWeekly(User $user): Collection
    {
        $week = now()->format('oW');

        return Cache::remember("recs:discover-weekly:{$user->id}:{$week}", now()->endOfWeek(), function () use ($user, $week) {
            $genreScores = $this->genreAffinity($user);

            if ($genreScores->isEmpty()) {
                return collect();
            }

            $excluded = $this->interactedTrackIds($user);

            $candidates = Track::with(['artist', 'album', 'genre'])
                ->ofType('music')
                ->approved()
                ->whereIn('genre_id', $genreScores->keys())
                ->whereNotIn('id', $excluded)
                ->limit(200)
                ->get();

            return $this->seededShuffle($candidates, "weekly:{$user->id}:{$week}")->take(self::DISCOVER_WEEKLY_SIZE)->values();
        });
    }

    public function recommendedPodcasts(?User $user): Collection
    {
        $genreScores = $user ? $this->genreAffinity($user) : collect();

        if ($genreScores->isNotEmpty()) {
            $matched = Track::with(['artist', 'album', 'genre'])
                ->ofType('podcast')
                ->approved()
                ->whereIn('genre_id', $genreScores->keys())
                ->orderByDesc('plays_count')
                ->limit(self::PODCAST_SIZE)
                ->get();

            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        return Track::with(['artist', 'album', 'genre'])
            ->ofType('podcast')
            ->approved()
            ->orderByDesc('plays_count')
            ->limit(self::PODCAST_SIZE)
            ->get();
    }

    /**
     * Other artists sharing genres with this one, boosted by co-listening
     * (users who played this artist's tracks also played theirs).
     */
    public function similarArtists(Artist $artist, int $limit = self::SIMILAR_ARTISTS_SIZE): Collection
    {
        return Cache::remember("recs:similar-artists:{$artist->id}:{$limit}", now()->addHours(6), function () use ($artist, $limit) {
            $genreIds = $artist->tracks()->pluck('genre_id')->filter()->unique();

            $scores = collect();

            if ($genreIds->isNotEmpty()) {
                Artist::query()
                    ->where('id', '!=', $artist->id)
                    ->whereHas('tracks', fn ($q) => $q->whereIn('genre_id', $genreIds))
                    ->withCount(['tracks as overlap_count' => fn ($q) => $q->whereIn('genre_id', $genreIds)])
                    ->get()
                    ->each(fn (Artist $candidate) => $scores[$candidate->id] = ($scores[$candidate->id] ?? 0) + $candidate->overlap_count * 2);
            }

            $listenerIds = PlayHistory::whereIn('track_id', $artist->tracks()->pluck('id'))->pluck('user_id')->unique();

            if ($listenerIds->isNotEmpty()) {
                PlayHistory::whereIn('user_id', $listenerIds)
                    ->join('tracks', 'tracks.id', '=', 'play_histories.track_id')
                    ->where('tracks.artist_id', '!=', $artist->id)
                    ->selectRaw('tracks.artist_id as artist_id, COUNT(*) as co_count')
                    ->groupBy('tracks.artist_id')
                    ->get()
                    ->each(fn ($row) => $scores[$row->artist_id] = ($scores[$row->artist_id] ?? 0) + $row->co_count);
            }

            if ($scores->isEmpty()) {
                return collect();
            }

            $topIds = $scores->sortDesc()->take($limit)->keys();

            return Artist::whereIn('id', $topIds)->get()->sortBy(fn (Artist $a) => array_search($a->id, $topIds->all()))->values();
        });
    }

    /**
     * A weighted genre_id => score map built from the user's listening
     * history (completed plays weighted higher than started-only), likes,
     * and follows.
     */
    private function genreAffinity(User $user): Collection
    {
        $scores = collect();

        PlayHistory::where('user_id', $user->id)
            ->join('tracks', 'tracks.id', '=', 'play_histories.track_id')
            ->whereNotNull('tracks.genre_id')
            ->selectRaw('tracks.genre_id as genre_id, SUM(CASE WHEN play_histories.completed THEN 3 ELSE 1 END) as weight')
            ->groupBy('tracks.genre_id')
            ->get()
            ->each(fn ($row) => $scores[$row->genre_id] = ($scores[$row->genre_id] ?? 0) + (int) $row->weight);

        Track::whereIn('id', function ($query) use ($user) {
            $query->select('favoritable_id')->from('favorites')
                ->where('user_id', $user->id)->where('favoritable_type', Track::class);
        })->whereNotNull('genre_id')->pluck('genre_id')
            ->each(fn ($genreId) => $scores[$genreId] = ($scores[$genreId] ?? 0) + 5);

        $followedArtistIds = Follow::where('user_id', $user->id)->where('followable_type', Artist::class)->pluck('followable_id');

        if ($followedArtistIds->isNotEmpty()) {
            Track::whereIn('artist_id', $followedArtistIds)->whereNotNull('genre_id')->pluck('genre_id')
                ->each(fn ($genreId) => $scores[$genreId] = ($scores[$genreId] ?? 0) + 2);
        }

        return $scores->sortDesc();
    }

    private function interactedTrackIds(User $user): Collection
    {
        $played = PlayHistory::where('user_id', $user->id)->pluck('track_id');
        $favorited = Favorite::where('user_id', $user->id)->where('favoritable_type', Track::class)->pluck('favoritable_id');

        return $played->merge($favorited)->unique()->values();
    }

    /**
     * Deterministic order for a given seed — same seed always yields the
     * same order, so "today's" or "this week's" mix stays stable across
     * requests without persisting anything.
     */
    private function seededShuffle(Collection $items, string $seed): Collection
    {
        return $items->sortBy(fn ($item) => md5("{$seed}-{$item->id}"))->values();
    }

    private function trending(?string $type, int $limit): Collection
    {
        return Track::with(['artist', 'album', 'genre'])
            ->approved()
            ->when($type, fn ($q) => $q->ofType($type))
            ->orderByDesc('plays_count')
            ->limit($limit)
            ->get();
    }
}
