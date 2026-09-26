<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\GenreResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\TrackResource;
use App\Models\Activity;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Tag;
use App\Models\Track;
use App\Notifications\NewFollowerNotification;
use App\Services\Community\ActivityLogger;
use App\Services\Recommendations\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(
        private ActivityLogger $activity,
        private RecommendationEngine $recommendations,
    ) {}

    public function genres(): AnonymousResourceCollection
    {
        return GenreResource::collection(Genre::orderBy('name')->get());
    }

    public function tags(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::orderBy('name')->get());
    }

    public function showArtist(Artist $artist): ArtistResource
    {
        $artist->loadCount('followers');

        return new ArtistResource($artist);
    }

    public function artistTracks(Artist $artist): AnonymousResourceCollection
    {
        return TrackResource::collection(
            $artist->tracks()->approved()->with(['artist', 'album', 'genre'])->latest('release_date')->paginate(20)
        );
    }

    public function similarArtists(Artist $artist): AnonymousResourceCollection
    {
        return ArtistResource::collection($this->recommendations->similarArtists($artist));
    }

    public function showAlbum(Album $album): AlbumResource
    {
        return new AlbumResource($album->load(['artist', 'genre', 'tracks.artist', 'tracks.album']));
    }

    public function showTrack(Request $request, Track $track): TrackResource
    {
        $track->load(['artist', 'album', 'genre', 'tags'])->loadCount(['comments', 'shares']);

        if ($track->status === 'rejected') {
            $user = $request->user('sanctum');
            abort_unless($user && ($user->id === $track->artist?->user_id || $user->can('moderate-content')), 404);
        }

        return new TrackResource($track);
    }

    public function followArtist(Request $request, Artist $artist): JsonResponse
    {
        $user = $request->user();

        $follow = $user->follows()->firstOrCreate([
            'followable_type' => Artist::class,
            'followable_id' => $artist->id,
        ]);

        if ($follow->wasRecentlyCreated) {
            $this->activity->log($user, Activity::TYPE_FOLLOWED_ARTIST, $artist);

            if ($artist->user && $artist->user_id !== $user->id) {
                $artist->user->notify(new NewFollowerNotification($user, $artist));
            }
        }

        return response()->json(['message' => 'Artist followed.']);
    }

    public function unfollowArtist(Request $request, Artist $artist): JsonResponse
    {
        $request->user()->follows()
            ->where('followable_type', Artist::class)
            ->where('followable_id', $artist->id)
            ->delete();

        return response()->json(['message' => 'Artist unfollowed.']);
    }
}
