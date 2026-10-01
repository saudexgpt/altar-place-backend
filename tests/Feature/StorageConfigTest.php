<?php

use Illuminate\Support\Facades\Storage;

// Regression guard for the DigitalOcean Spaces setup: FILESYSTEM_DISK_PUBLIC
// and FILESYSTEM_DISK_AUDIO switch these disks to 's3' in production (see
// config/filesystems.php and DEPLOYMENT.md). Resolving an 's3' disk requires
// the league/flysystem-aws-s3-v3 package — without it, Laravel throws
// "Driver [s3] is not supported" the first time any upload or stream touches
// the disk, which only surfaces live in production since local dev always
// runs these disks as 'local'. This test fails at the same point a real
// upload would, without needing real Spaces credentials or network access.
test('the public and audio disks can be resolved as s3 (DigitalOcean Spaces)', function () {
    config([
        'filesystems.disks.public.driver' => 's3',
        'filesystems.disks.public.key' => 'test-key',
        'filesystems.disks.public.secret' => 'test-secret',
        'filesystems.disks.public.region' => 'nyc3',
        'filesystems.disks.public.bucket' => 'test-bucket',
        'filesystems.disks.public.endpoint' => 'https://nyc3.digitaloceanspaces.com',

        'filesystems.disks.audio.driver' => 's3',
        'filesystems.disks.audio.key' => 'test-key',
        'filesystems.disks.audio.secret' => 'test-secret',
        'filesystems.disks.audio.region' => 'nyc3',
        'filesystems.disks.audio.bucket' => 'test-bucket',
        'filesystems.disks.audio.endpoint' => 'https://nyc3.digitaloceanspaces.com',
    ]);

    // Forgetting the resolved instances is required since the 'local'-driver
    // versions of these disks may already have been resolved and cached
    // earlier in the test suite.
    Storage::forgetDisk('public');
    Storage::forgetDisk('audio');

    expect(fn () => Storage::disk('public')->url('covers/example.jpg'))->not->toThrow(Throwable::class);
    // Mirrors StreamController's actual S3 code path (temporaryUrl, not
    // path() — the latter is unsupported on S3 and never called for it).
    expect(fn () => Storage::disk('audio')->temporaryUrl('audio/example.wav', now()->addMinutes(15)))
        ->not->toThrow(Throwable::class);
});
