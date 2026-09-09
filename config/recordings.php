<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Recording storage & transcription (ADR-026)
    |--------------------------------------------------------------------------
    | `fake` is deterministic and network-free for local dev and tests. `s3`
    | issues presigned URLs against the `disk`; `http` transcription calls an
    | external provider. Both real providers need per-environment config.
    */
    'storage' => env('RECORDINGS_STORAGE', 'fake'), // fake|s3
    'disk' => env('RECORDINGS_DISK', 's3'),

    'transcriber' => env('RECORDINGS_TRANSCRIBER', 'fake'), // fake|http
    'http' => [
        'endpoint' => env('TRANSCRIBER_ENDPOINT', ''),
        'api_key' => env('TRANSCRIBER_API_KEY', ''),
    ],
];
