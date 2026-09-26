<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlaylistRequest;
use App\Http\Resources\PlaylistResource;
use App\Models\Activity;
use App\Models\Playlist;
use App\Models\Track;
use App\Services\Community\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaylistController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user('sanctum')?->id;

        $playlists = Playlist::withCount('tracks')
            ->where(function ($query) use ($userId) {
                $query->where('is_public', true)->orWhere('user_id', $userId);
            })
            ->latest()
            ->paginate(20);

        return PlaylistResource::collection($playlists);
    }

    public function store(PlaylistRequest $request): JsonResponse
    {
        $playlist = $request->user()->playlists()->create($this->authorizedFields($request));

        $this->activity->log($request->user(), Activity::TYPE_CREATED_PLAYLIST, $playlist);

        return (new PlaylistResource($playlist->fresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, Playlist $playlist): PlaylistResource
    {
        $isOwner = $playlist->user_id === $request->user('sanctum')?->id;
        abort_unless($playlist->is_public || $isOwner, 403);

        $playlist->load(['user', 'tracks.artist', 'tracks.album'])->loadCount('shares');

        return new PlaylistResource($playlist);
    }

    public function update(PlaylistRequest $request, Playlist $playlist): PlaylistResource
    {
        $this->authorize('update', $playlist);

        $playlist->update($this->authorizedFields($request));

        return new PlaylistResource($playlist->fresh());
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        $this->authorize('delete', $playlist);

        $playlist->delete();

        return response()->json(['message' => 'Playlist deleted.']);
    }

    public function addTrack(Request $request, Playlist $playlist, Track $track): PlaylistResource
    {
        $this->authorize('manageTracks', $playlist);

        $nextPosition = $playlist->tracks()->count();
        $playlist->tracks()->syncWithoutDetaching([$track->id => ['position' => $nextPosition]]);

        return new PlaylistResource($playlist->fresh(['tracks.artist', 'tracks.album']));
    }

    public function removeTrack(Playlist $playlist, Track $track): PlaylistResource
    {
        $this->authorize('manageTracks', $playlist);

        $playlist->tracks()->detach($track->id);

        return new PlaylistResource($playlist->fresh(['tracks.artist', 'tracks.album']));
    }

    public function follow(Request $request, Playlist $playlist): JsonResponse
    {
        $follow = $request->user()->follows()->firstOrCreate([
            'followable_type' => Playlist::class,
            'followable_id' => $playlist->id,
        ]);

        if ($follow->wasRecentlyCreated) {
            $this->activity->log($request->user(), Activity::TYPE_FOLLOWED_PLAYLIST, $playlist);
        }

        return response()->json(['message' => 'Playlist followed.']);
    }

    public function unfollow(Request $request, Playlist $playlist): JsonResponse
    {
        $request->user()->follows()
            ->where('followable_type', Playlist::class)
            ->where('followable_id', $playlist->id)
            ->delete();

        return response()->json(['message' => 'Playlist unfollowed.']);
    }

    public function share(Request $request, Playlist $playlist): JsonResponse
    {
        $request->validate(['platform' => ['nullable', 'string', 'max:50']]);

        $playlist->shares()->create([
            'user_id' => $request->user()->id,
            'platform' => $request->input('platform'),
        ]);

        $this->activity->log($request->user(), Activity::TYPE_SHARED_PLAYLIST, $playlist);

        return response()->json(['message' => 'Share logged.'], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizedFields(PlaylistRequest $request): array
    {
        $data = $request->validated();

        if (! $request->user()->can('moderate-content')) {
            unset($data['is_curated']);
        }

        return $data;
    }
}
