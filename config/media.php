<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Media provider
    |--------------------------------------------------------------------------
    | The media plane (ADR-002). `fake` is deterministic and network-free for
    | local dev and tests; `livekit` is the production provider.
    */
    'provider' => env('MEDIA_PROVIDER', 'fake'),

    'livekit' => [
        'api_key' => env('LIVEKIT_API_KEY', ''),
        'api_secret' => env('LIVEKIT_API_SECRET', ''),
        // Client-facing WebSocket URL (wss://...).
        'url' => env('LIVEKIT_URL', ''),
        // Server API host for RoomService calls (https://...).
        'host' => env('LIVEKIT_HOST', ''),
        'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 3600),
    ],
];
