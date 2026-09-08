<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    | Configured for first-party SPA (Sanctum stateful cookie) auth. Credentials
    | are allowed, so origins must be explicit (never "*").
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id', 'X-Correlation-Id'],

    'max_age' => 0,

    'supports_credentials' => true,
];
