<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics capture (ADR-005 / ADR-031)
    |--------------------------------------------------------------------------
    | `sync` writes the analytics row inside the request (default, simplest).
    | `queue` pushes the write to a job so the request is not coupled to the
    | analytics store — the scale path (resolves TD-016). Both sit behind the
    | same AnalyticsCollector contract, so callers never change.
    */
    'capture' => env('ANALYTICS_CAPTURE', 'sync'), // sync|queue

    /*
    |--------------------------------------------------------------------------
    | Analytics extraction (warehouse)
    |--------------------------------------------------------------------------
    | The `analytics:extract` command ships unexported rows to a warehouse sink
    | behind the AnalyticsExporter contract. `fake` is network-free (default);
    | `clickhouse` is a per-environment HTTP sink (not integration-tested here).
    */
    'export' => env('ANALYTICS_EXPORT', 'fake'), // fake|clickhouse
    'export_batch' => (int) env('ANALYTICS_EXPORT_BATCH', 500),

    'clickhouse' => [
        'endpoint' => env('CLICKHOUSE_ENDPOINT', ''),
        'database' => env('CLICKHOUSE_DATABASE', 'default'),
        'table' => env('CLICKHOUSE_TABLE', 'analytics_events'),
        'username' => env('CLICKHOUSE_USERNAME', ''),
        'password' => env('CLICKHOUSE_PASSWORD', ''),
    ],
];
