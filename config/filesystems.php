<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // Pure local scratch space — never the source of truth for anything.
        // TranscodeAudioJob uses this as ffmpeg's working directory even when
        // the 'audio' disk below is S3-backed, since ffmpeg needs real local
        // paths to read/write.
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Cover art, avatars, ad creatives. Local in dev; set
        // FILESYSTEM_DISK_PUBLIC=s3 in production to serve these from DO
        // Spaces (or any S3-compatible store) instead of the container's
        // ephemeral disk.
        'public' => [
            'driver' => env('FILESYSTEM_DISK_PUBLIC', 'local'),
            'root' => env('FILESYSTEM_DISK_PUBLIC') === 's3' ? 'public' : storage_path('app/public'),
            'url' => env('FILESYSTEM_DISK_PUBLIC') === 's3'
                ? env('AWS_URL')
                : rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Raw uploaded audio + generated HLS renditions — the app's actual
        // content, so this is the one disk that must be durable. Local in
        // dev/testing (rooted at the same path 'local' used to use, so
        // existing fixtures/tests are unaffected); set
        // FILESYSTEM_DISK_AUDIO=s3 in production so it survives redeploys on
        // platforms with no persistent disk (e.g. DO App Platform).
        'audio' => [
            'driver' => env('FILESYSTEM_DISK_AUDIO', 'local'),
            'root' => env('FILESYSTEM_DISK_AUDIO') === 's3' ? 'audio' : storage_path('app/private'),
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'temporary_url_expiration' => 900,
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
