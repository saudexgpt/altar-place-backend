<?php

namespace App\Jobs;

use App\Models\Track;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\File as FileFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Transcodes an uploaded track into an adaptive-bitrate HLS rendition
 * (multiple audio bitrates behind one master playlist) using ffmpeg.
 *
 * The 'audio' disk (see config/filesystems.php) may be local disk or an
 * S3-compatible store like DO Spaces — either way, ffmpeg needs real local
 * filesystem paths to read/write, so this job always stages the source file
 * and generated renditions through a scratch directory on local disk, then
 * persists the results back to whichever disk 'audio' actually is.
 *
 * ffmpeg is not guaranteed to be present on every environment this runs in
 * (e.g. local dev without it installed). When it's missing or the command
 * fails, the track is marked `failed` and direct-file streaming (already
 * fully functional via StreamController) remains the playback path — HLS is
 * a progressive enhancement, not a hard dependency for playback.
 */
class TranscodeAudioJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Track $track) {}

    public function handle(): void
    {
        $this->track->update(['transcoding_status' => 'processing']);

        $audioDisk = Storage::disk('audio');
        $workDir = storage_path('app/private/tmp/transcode-'.$this->track->id.'-'.Str::random(8));
        $localOutputDir = "{$workDir}/output";
        FileFacade::ensureDirectoryExists($localOutputDir);

        try {
            $extension = pathinfo($this->track->audio_path, PATHINFO_EXTENSION) ?: 'audio';
            $localSourcePath = "{$workDir}/source.{$extension}";
            $this->stageLocally($audioDisk, $this->track->audio_path, $localSourcePath);

            $binary = config('services.ffmpeg.path', 'ffmpeg');

            // Three audio-only renditions behind one adaptive master playlist —
            // the HLS equivalent of "adaptive bitrate streaming" for audio.
            $renditions = ['64k', '128k', '192k'];
            $streamMap = implode(' ', array_map(fn ($i) => "a:{$i},agroup:audio", array_keys($renditions)));
            $bitrateArgs = [];
            foreach ($renditions as $index => $bitrate) {
                $bitrateArgs[] = "-map 0:a -c:a:{$index} aac -b:a:{$index} {$bitrate}";
            }

            $command = sprintf(
                '%s -y -i %s %s -var_stream_map %s -hls_time 6 -hls_playlist_type vod -hls_segment_filename %s %s',
                escapeshellarg($binary),
                escapeshellarg($localSourcePath),
                implode(' ', $bitrateArgs),
                escapeshellarg($streamMap),
                escapeshellarg("{$localOutputDir}/segment_%v_%03d.ts"),
                escapeshellarg("{$localOutputDir}/stream_%v.m3u8")
            );

            $result = Process::timeout(600)->run($command);

            if (! $result->successful()) {
                Log::warning('Audio transcoding failed; falling back to direct streaming.', [
                    'track_id' => $this->track->id,
                    'error' => $result->errorOutput() ?: 'ffmpeg binary not found or command failed.',
                ]);

                $this->track->update(['transcoding_status' => 'failed']);

                return;
            }

            $this->writeMasterPlaylist($localOutputDir, $renditions);

            $outputDir = "hls/{$this->track->id}";
            foreach (FileFacade::allFiles($localOutputDir) as $file) {
                $audioDisk->putFileAs($outputDir, new File($file->getPathname()), $file->getFilename());
            }

            $this->track->update([
                'transcoding_status' => 'ready',
                'hls_playlist_path' => "{$outputDir}/master.m3u8",
            ]);
        } finally {
            FileFacade::deleteDirectory($workDir);
        }
    }

    /**
     * Copies a file from the (possibly remote) 'audio' disk onto real local
     * disk, since ffmpeg needs an actual filesystem path to read from.
     */
    private function stageLocally(Filesystem $disk, string $remotePath, string $localPath): void
    {
        $source = $disk->readStream($remotePath);
        $destination = fopen($localPath, 'w');

        stream_copy_to_stream($source, $destination);

        fclose($destination);

        if (is_resource($source)) {
            fclose($source);
        }
    }

    /**
     * @param  list<string>  $renditions
     */
    private function writeMasterPlaylist(string $outputAbsoluteDir, array $renditions): void
    {
        $lines = ['#EXTM3U'];

        foreach (array_keys($renditions) as $index) {
            $bandwidth = (int) rtrim($renditions[$index], 'k') * 1000;
            $lines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},AUDIO=\"audio\"";
            $lines[] = "stream_{$index}.m3u8";
        }

        file_put_contents("{$outputAbsoluteDir}/master.m3u8", implode("\n", $lines).\PHP_EOL);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Transcoding job failed with an exception.', [
            'track_id' => $this->track->id,
            'message' => $exception->getMessage(),
        ]);

        $this->track->update(['transcoding_status' => 'failed']);
    }
}
