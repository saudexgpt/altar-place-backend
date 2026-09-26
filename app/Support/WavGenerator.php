<?php

namespace App\Support;

/**
 * Synthesizes short placeholder WAV tones for the Phase 1 demo catalog.
 * There is no real licensed audio (and no ffmpeg in this environment) to
 * seed the catalog with, so tracks play a distinguishable sine-wave tone
 * long enough to exercise real streaming, seeking, and playback UI. Replace
 * with real uploads once the Phase 2 creator upload pipeline exists.
 */
class WavGenerator
{
    private const SAMPLE_RATE = 11025;

    public static function make(string $absolutePath, int $seconds, float $frequency = 440.0): void
    {
        $sampleRate = self::SAMPLE_RATE;
        $numSamples = $sampleRate * $seconds;
        $amplitude = 12000;

        $data = '';
        for ($i = 0; $i < $numSamples; $i++) {
            // Fade in/out over 0.3s to avoid audible clicks at track boundaries.
            $fadeSamples = (int) (0.3 * $sampleRate);
            $envelope = 1.0;
            if ($i < $fadeSamples) {
                $envelope = $i / $fadeSamples;
            } elseif ($i > $numSamples - $fadeSamples) {
                $envelope = ($numSamples - $i) / $fadeSamples;
            }

            $sample = (int) ($amplitude * $envelope * sin(2 * M_PI * $frequency * $i / $sampleRate));
            $data .= pack('v', $sample & 0xFFFF);
        }

        $dataSize = strlen($data);
        $header = 'RIFF'.pack('V', 36 + $dataSize).'WAVE';
        $header .= 'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', $sampleRate).pack('V', $sampleRate * 2).pack('v', 2).pack('v', 16);
        $header .= 'data'.pack('V', $dataSize);

        file_put_contents($absolutePath, $header.$data);
    }
}
