<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadTrackRequest;
use App\Http\Resources\TrackResource;
use App\Jobs\TranscodeAudioJob;
use App\Models\Artist;
use App\Services\TrackUploader;
use Illuminate\Http\JsonResponse;

class AdminTrackController extends Controller
{
    public function __construct(private readonly TrackUploader $uploader) {}

    /**
     * Lets staff publish a platform resource (music or Word content)
     * directly, without needing a personal creator profile — credited to
     * the shared "Altar Place" artist so it reads clearly as official
     * content. Reuses the same storage/transcoding pipeline as creator
     * uploads, so it surfaces to every listener on web and mobile through
     * the same discovery/search/catalog endpoints they already use.
     */
    public function store(UploadTrackRequest $request): JsonResponse
    {
        $track = $this->uploader->upload($request, Artist::official());

        TranscodeAudioJob::dispatch($track);

        return (new TrackResource($track->fresh(['artist', 'album', 'genre', 'tags'])))
            ->response()->setStatusCode(201);
    }
}
