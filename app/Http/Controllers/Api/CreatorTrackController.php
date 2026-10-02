<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTrackRequest;
use App\Http\Requests\Api\UploadTrackRequest;
use App\Http\Resources\TrackResource;
use App\Jobs\TranscodeAudioJob;
use App\Models\Track;
use App\Services\TrackUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CreatorTrackController extends Controller
{
    public function __construct(private readonly TrackUploader $uploader) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $artist = $request->user()->artist()->firstOrFail();

        return TrackResource::collection(
            $artist->tracks()->with(['artist', 'album', 'genre', 'tags'])->latest()->paginate(20)
        );
    }

    public function store(UploadTrackRequest $request): JsonResponse
    {
        $artist = $request->user()->artist()->first();
        abort_unless($artist, 403, 'You need a creator profile before uploading content.');

        $track = $this->uploader->upload($request, $artist);

        TranscodeAudioJob::dispatch($track);

        return (new TrackResource($track->fresh(['artist', 'album', 'genre', 'tags'])))
            ->response()->setStatusCode(201);
    }

    public function update(UpdateTrackRequest $request, Track $track): TrackResource
    {
        $this->authorize('update', $track);

        $track->update($request->safe()->except(['cover', 'tags']));

        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('covers', 'public');
            $track->update(['cover_url' => Storage::disk('public')->url($coverPath)]);
        }

        if ($request->has('tags')) {
            $this->uploader->syncTags($track, $request->input('tags', []));
        }

        return new TrackResource($track->fresh(['artist', 'album', 'genre', 'tags']));
    }

    public function destroy(Track $track): JsonResponse
    {
        $this->authorize('delete', $track);

        Storage::disk('audio')->delete($track->audio_path);
        $track->delete();

        return response()->json(['message' => 'Track deleted.']);
    }
}
