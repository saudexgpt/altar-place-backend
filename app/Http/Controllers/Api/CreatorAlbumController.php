<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AlbumRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreatorAlbumController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $artist = $request->user()->artist()->firstOrFail();

        return AlbumResource::collection(
            $artist->albums()->with(['genre'])->withCount('tracks')->latest()->get()
        );
    }

    public function store(AlbumRequest $request): JsonResponse
    {
        $artist = $request->user()->artist()->first();
        abort_unless($artist, 403, 'You need a creator profile before creating an album.');

        $coverUrl = null;
        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('covers', 'public');
            $coverUrl = Storage::disk('public')->url($coverPath);
        }

        $album = Album::create([
            'artist_id' => $artist->id,
            'genre_id' => $request->input('genre_id'),
            'title' => $request->string('title'),
            'slug' => Str::slug($request->string('title')).'-'.Str::random(6),
            'type' => $request->input('type', 'album'),
            'cover_url' => $coverUrl,
            'description' => $request->input('description'),
            'release_year' => $request->input('release_year'),
        ]);

        return (new AlbumResource($album))->response()->setStatusCode(201);
    }

    public function update(AlbumRequest $request, Album $album): AlbumResource
    {
        $this->authorize('update', $album);

        $album->update($request->safe()->except('cover'));

        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('covers', 'public');
            $album->update(['cover_url' => Storage::disk('public')->url($coverPath)]);
        }

        return new AlbumResource($album->fresh());
    }

    public function destroy(Album $album): JsonResponse
    {
        $this->authorize('delete', $album);

        $album->delete();

        return response()->json(['message' => 'Album deleted.']);
    }
}
