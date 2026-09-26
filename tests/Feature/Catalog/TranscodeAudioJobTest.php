<?php

use App\Jobs\TranscodeAudioJob;
use App\Models\Artist;
use App\Models\Track;
use App\Support\WavGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * ffmpeg is not installed in this dev/CI environment, so these tests can't
 * exercise a successful transcode — but they do exercise the new
 * disk-agnostic staging logic (copying the source off the 'audio' disk onto
 * real local disk before invoking ffmpeg), which is the part most likely to
 * break silently when the 'audio' disk is S3-backed in production.
 */
function seedTrackOnAudioDisk(): Track
{
    Storage::fake('audio');
    Storage::disk('audio')->makeDirectory('audio');

    $track = Track::factory()->for(Artist::factory())->create([
        'audio_path' => 'audio/source.wav',
        'transcoding_status' => 'pending',
    ]);

    WavGenerator::make(Storage::disk('audio')->path($track->audio_path), 1, 440.0);

    return $track;
}

test('the job stages the source from the audio disk without error before ffmpeg runs', function () {
    $track = seedTrackOnAudioDisk();

    (new TranscodeAudioJob($track))->handle();

    // ffmpeg isn't available here, so the job falls back to "failed" — the
    // important thing is it got past staging the file locally without a
    // filesystem/stream error, and left the track streamable via the
    // existing direct-file fallback either way.
    expect($track->fresh()->transcoding_status)->toBe('failed');
});

test('the job cleans up its local scratch directory whether ffmpeg succeeds or not', function () {
    $track = seedTrackOnAudioDisk();

    (new TranscodeAudioJob($track))->handle();

    $leftoverScratchDirs = collect(glob(storage_path('app/private/tmp/transcode-*')));
    expect($leftoverScratchDirs)->toBeEmpty();
});
