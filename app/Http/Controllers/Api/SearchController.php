<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\GenreResource;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\TrackResource;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\SearchHistory;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Unified search across artists, tracks (music/podcast/sermon),
     * playlists, and genres/categories. `type=all` (default) returns a
     * short preview of each category; passing a specific `type` returns a
     * fuller paginated list for that category alone.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['nullable', 'in:all,artist,track,podcast,sermon,playlist,genre'],
        ]);

        $term = $request->string('q')->toString();
        $type = $request->string('type', 'all')->toString();
        $previewLimit = $type === 'all' ? 5 : 20;

        if ($user = $request->user('sanctum')) {
            SearchHistory::create(['user_id' => $user->id, 'query' => $term]);
        }

        $result = [];

        if (in_array($type, ['all', 'artist'], true)) {
            $result['artists'] = ArtistResource::collection(
                Artist::where('name', 'like', "%{$term}%")->limit($previewLimit)->get()
            );
        }

        if (in_array($type, ['all', 'track'], true)) {
            $result['tracks'] = TrackResource::collection(
                Track::with(['artist', 'album', 'genre'])->ofType('music')->approved()
                    ->where('title', 'like', "%{$term}%")->limit($previewLimit)->get()
            );
        }

        if (in_array($type, ['all', 'podcast'], true)) {
            $result['podcasts'] = TrackResource::collection(
                Track::with(['artist', 'album', 'genre'])->ofType('podcast')->approved()
                    ->where('title', 'like', "%{$term}%")->limit($previewLimit)->get()
            );
        }

        if (in_array($type, ['all', 'sermon'], true)) {
            $result['sermons'] = TrackResource::collection(
                Track::with(['artist', 'album', 'genre'])->ofType('sermon')->approved()
                    ->where('title', 'like', "%{$term}%")->limit($previewLimit)->get()
            );
        }

        if (in_array($type, ['all', 'playlist'], true)) {
            $result['playlists'] = PlaylistResource::collection(
                Playlist::where('is_public', true)
                    ->where('title', 'like', "%{$term}%")->limit($previewLimit)->get()
            );
        }

        if (in_array($type, ['all', 'genre'], true)) {
            $result['genres'] = GenreResource::collection(
                Genre::where('name', 'like', "%{$term}%")->limit($previewLimit)->get()
            );
        }

        return response()->json($result);
    }
}
