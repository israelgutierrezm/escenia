<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AI providers (ADR-027)
    |--------------------------------------------------------------------------
    | `fake`/`database` are deterministic and network-free for local dev and
    | tests (semantic search works for real via cosine in the DB). The real
    | providers (claude/http/qdrant) need per-environment config.
    */
    'completion' => env('AI_COMPLETION', 'fake'), // fake|claude
    'embedding' => env('AI_EMBEDDING', 'fake'),    // fake|http
    'vector' => env('AI_VECTOR', 'database'),      // database|qdrant

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
        'model' => env('AI_CLAUDE_MODEL', 'claude-opus-5'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 4096),
    ],

    'embeddings' => [
        'endpoint' => env('AI_EMBEDDING_ENDPOINT', ''),
        'api_key' => env('AI_EMBEDDING_KEY', ''),
        'model' => env('AI_EMBEDDING_MODEL', ''),
        'dimensions' => (int) env('AI_EMBEDDING_DIMS', 1024),
    ],

    'qdrant' => [
        'url' => env('QDRANT_URL', ''),
        'api_key' => env('QDRANT_KEY', ''),
        'collection' => env('QDRANT_COLLECTION', 'content'),
    ],
];
