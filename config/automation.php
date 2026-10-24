<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Step retries (ADR-038)
    |--------------------------------------------------------------------------
    | Seconds to wait before each new attempt of the SAME step. A retry blocks
    | the steps after it (the sequence keeps its order), so the windows are
    | bounded: a webhook gets 6 attempts over about 7.6 hours (the start of the
    | Standard Webhooks example schedule), then it gives up and the sequence
    | goes on; a step that throws gets 4 attempts over about 36 minutes, then
    | the run fails. Every delay gets up to `jitter` (10 %) extra so parked runs
    | do not hit a recovering receiver at once; a receiver's Retry-After is
    | honoured up to `max_retry_after`.
    */
    'retries' => [
        'webhook' => [60, 300, 1800, 7200, 18000],
        'error' => [60, 300, 1800],
        'max_retry_after' => 18000,
        'jitter' => 0.1,
    ],
];
