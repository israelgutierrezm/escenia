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

    // Max upload size (KB) and accepted image types for a brand-kit logo.
    'logo_max_kb' => (int) env('BRAND_LOGO_MAX_KB', 1024),
    'logo_mimes' => ['png', 'jpg', 'jpeg', 'gif', 'svg'],
];
