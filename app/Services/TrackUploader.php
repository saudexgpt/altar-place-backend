<?php

namespace App\Services;

use App\Http\Requests\Api\UploadTrackRequest;
use App\Models\Artist;
use App\Models\Tag;
use App\Models\Track;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrackUploader
{
    /**
     * Shared by CreatorTrackController (artist uploading their own content)
     * and AdminTrackController (staff publishing platform resources) — the
     * storage/transcoding-prep steps are identical either way, only the
     * owning Artist differs.
     */
    public function upload(UploadTrackRequest $request, Artist $artist): Track
    {
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

        return $track;
    }

    /**
     * Also used by CreatorTrackController::update() when editing an
     * existing track's tags.
     *
     * @param  list<string>  $tagNames
     */
    public function syncTags(Track $track, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->filter()
            ->map(fn (string $name) => Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => trim($name)]
            )->id);

        $track->tags()->sync($tagIds);
    }

    private function extractDurationSeconds(string $absolutePath): int
    {
        $getID3 = new \getID3;
        $fileInfo = $getID3->analyze($absolutePath);

        return (int) round($fileInfo['playtime_seconds'] ?? 0);
    }
}
