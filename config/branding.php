<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Brand assets
    |--------------------------------------------------------------------------
    | Disk where uploaded brand-kit logos are stored (a Laravel filesystem disk:
    | `local` offline, `s3` in production). Logos are read back server-side and
    | embedded as data URIs (certificate PDF, admin preview) — the disk need not
    | be public.
    */
    'logo_disk' => env('BRAND_LOGO_DISK', 'local'),

    // Upload limits for a brand-kit logo: file size (KB) and pixel dimensions.
    // The pixel cap protects the PDF worker from decompression bombs (a small
    // PNG that decodes to a huge bitmap). Accepted formats are a domain rule:
    // see BrandKit::LOGO_MIME_EXTENSIONS.
    'logo_max_kb' => (int) env('BRAND_LOGO_MAX_KB', 1024),
    'logo_max_px' => (int) env('BRAND_LOGO_MAX_PX', 2000),
];
