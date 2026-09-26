<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\TrackResource;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Track;
use App\Services\Recommendations\RecommendationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiscoveryController extends Controller
{
    public function __construct(private RecommendationEngine $recommendations) {}

    /**
     * Shared "All / Music / The Word / Podcasts" category filter used by the
     * Home dashboard's pills across every discovery endpoint.
     *
     * @param  Builder<Track>  $query
     * @return Builder<Track>
     */
    private function applyCategory(Builder $query, ?string $category): Builder
    {
        return match ($category) {
            'music' => $query->where('type', 'music'),
            'the-word' => $query->where('type', 'sermon'),
            'podcasts' => $query->where('type', 'podcast'),
            default => $query,
        };
    }

    public function trending(Request $request): AnonymousResourceCollection
    {
        $query = Track::with(['artist', 'album', 'genre'])->approved()->orderByDesc('plays_count');

        return TrackResource::collection($this->applyCategory($query, $request->query('category'))->limit(20)->get());
    }

    public function newReleases(Request $request): AnonymousResourceCollection
    {
        $query = Track::with(['artist', 'album', 'genre'])->approved()->latest('release_date');

        return TrackResource::collection($this->applyCategory($query, $request->query('category'))->limit(20)->get());
    }

    public function recommended(Request $request): AnonymousResourceCollection
    {
        $category = $request->query('category');
        $type = match ($category) {
            'music' => 'music',
            'the-word' => 'sermon',
            'podcasts' => 'podcast',
            default => null,
        };

        return TrackResource::collection(
            $this->recommendations->forYou($request->user('sanctum'), $type)
        );
    }

    public function dailyMix(Request $request): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');
        abort_unless($user, 401, 'Sign in to get your Daily Mix.');

        return TrackResource::collection($this->recommendations->dailyMix($user));
    }

    public function discoverWeekly(Request $request): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');
        abort_unless($user, 401, 'Sign in to get your Discover Weekly.');

        return TrackResource::collection($this->recommendations->discoverWeekly($user));
    }

    public function recommendedPodcasts(Request $request): AnonymousResourceCollection
    {
        return TrackResource::collection(
            $this->recommendations->recommendedPodcasts($request->user('sanctum'))
        );
    }

    public function featuredArtists(): AnonymousResourceCollection
    {
        $artists = Artist::withCount('followers')
            ->where('is_verified', true)
            ->orderByDesc('followers_count')
            ->limit(10)
            ->get();

        return ArtistResource::collection($artists);
    }

    public function featuredPodcasts(): AnonymousResourceCollection
    {
        return TrackResource::collection(
            Track::with(['artist', 'album', 'genre'])
                ->ofType('podcast')
                ->approved()
                ->latest('release_date')
                ->limit(20)
                ->get()
        );
    }

    public function featuredSermons(): AnonymousResourceCollection
    {
        return TrackResource::collection(
            Track::with(['artist', 'album', 'genre'])
                ->ofType('sermon')
                ->approved()
                ->latest('release_date')
                ->limit(20)
                ->get()
        );
    }

    public function popularPlaylists(): AnonymousResourceCollection
    {
        $playlists = Playlist::withCount('tracks')
            ->where('is_curated', true)
            ->where('is_public', true)
            ->orderByDesc('tracks_count')
            ->limit(20)
            ->get();

        return PlaylistResource::collection($playlists);
    }
}
