<?php

namespace App\Services\Ads;

use App\Models\Advertisement;
use App\Models\PlayHistory;
use App\Models\User;

class AdSelector
{
    /**
     * Picks one active, targeting-eligible campaign for the given placement
     * and request context, or null when nothing qualifies. When several
     * campaigns qualify, one is chosen at random — fair pacing/weighting by
     * remaining budget is a future enhancement.
     */
    public function select(string $type, ?User $user, ?string $deviceType = null): ?Advertisement
    {
        $listenerGenreId = $user ? $this->topGenreIdFor($user) : null;

        $eligible = Advertisement::query()
            ->ofType($type)
            ->active()
            ->with('sponsorable')
            ->get()
            ->reject(fn (Advertisement $ad) => $ad->hasReachedDailyCap())
            ->filter(fn (Advertisement $ad) => $ad->matches($user, $deviceType, $listenerGenreId));

        return $eligible->isEmpty() ? null : $eligible->random();
    }

    /**
     * The genre the listener has played the most, used as the "genre
     * interest" targeting signal. Null when they have no listening history.
     */
    private function topGenreIdFor(User $user): ?int
    {
        return PlayHistory::query()
            ->where('user_id', $user->id)
            ->join('tracks', 'tracks.id', '=', 'play_histories.track_id')
            ->whereNotNull('tracks.genre_id')
            ->selectRaw('tracks.genre_id, COUNT(*) as play_count')
            ->groupBy('tracks.genre_id')
            ->orderByDesc('play_count')
            ->value('tracks.genre_id');
    }
}
