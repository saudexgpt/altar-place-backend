<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTrackRequest;
use App\Http\Requests\Api\UploadTrackRequest;
use App\Http\Resources\TrackResource;
use App\Jobs\TranscodeAudioJob;
use App\Models\Tag;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreatorTrackController extends Controller
{
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

        $audioFile = $request->file('audio');
        $slug = Str::slug($request->string('title')).'-'.Str::random(6);
        $extension = $audioFile->getClientOriginalExtension();
        $relativePath = "audio/{$slug}.{$extension}";

        // Analyze duration from the original PHP upload tmp file before it
        // moves anywhere — works the same whether the 'audio' disk ends up
        // being local or S3, since getID3 needs a real local path either way.
        $duration = $this->extractDurationSeconds($audioFile->getRealPath());

        $audioFile->storeAs('audio', "{$slug}.{$extension}", 'audio');

        $coverUrl = null;
        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('covers', 'public');
            $coverUrl = Storage::disk('public')->url($coverPath);
        }

        $track = Track::create([
            'artist_id' => $artist->id,
            'album_id' => $request->input('album_id'),
            'genre_id' => $request->input('genre_id'),
            'title' => $request->string('title'),
            'slug' => $slug,
            'type' => $request->string('type'),
            'language' => $request->input('language', 'English'),
            'audio_path' => $relativePath,
            'duration_seconds' => $duration,
            'cover_url' => $coverUrl,
            'description' => $request->input('description'),
            'is_explicit' => $request->boolean('is_explicit'),
            'release_date' => $request->input('release_date', now()->toDateString()),
            'transcoding_status' => 'pending',
        ]);

        $this->syncTags($track, $request->input('tags', []));

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
            $this->syncTags($track, $request->input('tags', []));
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

    private function extractDurationSeconds(string $absolutePath): int
    {
        $getID3 = new \getID3;
        $fileInfo = $getID3->analyze($absolutePath);

        return (int) round($fileInfo['playtime_seconds'] ?? 0);
    }

    /**
     * @param  list<string>  $tagNames
     */
    private function syncTags(Track $track, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->filter()
            ->map(fn (string $name) => Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => trim($name)]
            )->id);

        $track->tags()->sync($tagIds);
    }
}
