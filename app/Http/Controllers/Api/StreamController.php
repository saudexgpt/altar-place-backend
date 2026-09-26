<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayHistory;
use App\Models\Track;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StreamController extends Controller
{
    /**
     * Stream a track's audio file. When the 'audio' disk is local (dev, or a
     * Droplet with persistent disk), this serves the file directly with HTTP
     * Range support (seeking) via Symfony's BinaryFileResponse. When it's an
     * S3-compatible store (e.g. DO Spaces), the client is redirected to a
     * short-lived signed URL instead — the object store handles Range
     * requests itself, and bytes never pass through this server.
     *
     * A play is only logged for the initial request (no Range header, or a
     * range starting at byte 0) so that the browser's chunked range requests
     * for the same playback don't inflate play counts.
     */
    public function stream(Request $request, Track $track): BinaryFileResponse|RedirectResponse
    {
        $disk = Storage::disk('audio');

        abort_unless($disk->exists($track->audio_path), 404, 'Audio file not found.');

        if ($track->status === 'rejected') {
            $user = $request->user('sanctum');
            abort_unless($user && ($user->id === $track->artist?->user_id || $user->can('moderate-content')), 404, 'This track is not available.');
        }

        $range = $request->header('Range');
        $isInitialRequest = $range === null || str_starts_with($range, 'bytes=0-');

        if ($isInitialRequest) {
            $track->increment('plays_count');

            // This route is intentionally public (guests can stream per the
            // Guest role), so resolve the user from a Bearer token if present
            // without requiring the `auth:sanctum` middleware to enforce it.
            if ($user = $request->user('sanctum')) {
                PlayHistory::create([
                    'user_id' => $user->id,
                    'track_id' => $track->id,
                    'played_at' => now(),
                ]);
            }
        }

        if (config('filesystems.disks.audio.driver') === 's3') {
            return redirect()->away($disk->temporaryUrl($track->audio_path, now()->addMinutes(15)));
        }

        return response()->file($disk->path($track->audio_path), [
            'Content-Type' => 'audio/wav',
        ]);
    }
}
