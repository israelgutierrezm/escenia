<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Certificates (ADR-028 / TD-031)
    |--------------------------------------------------------------------------
    | Certificates are pre-generated to PDF on issuance by a queued job and
    | stored on `certificate_disk` (a Laravel filesystem disk: `local` offline,
    | `s3` in production). The download endpoint serves the stored file and falls
    | back to an on-demand render while it is missing. Set `pregenerate` off to
    | rely purely on on-demand rendering.
    */
    'certificate_disk' => env('CERTIFICATE_DISK', 'local'),
    'pregenerate' => (bool) env('CERTIFICATE_PREGENERATE', true),
];
