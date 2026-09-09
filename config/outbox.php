<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Outbox event-stream fan-out (ADR-007 / ADR-031)
    |--------------------------------------------------------------------------
    | After the internal handlers run, the dispatcher can also publish each event
    | to an external stream so other services/regions can consume it. `null` is a
    | no-op (default — no infra before it is needed); `log` records the event;
    | `kafka` produces to Kafka/Redpanda via a REST proxy (not integration-tested).
    */
    'stream' => env('OUTBOX_STREAM', 'null'), // null|log|kafka

    'kafka' => [
        'rest_proxy' => env('KAFKA_REST_PROXY', ''),
        'topic_prefix' => env('KAFKA_TOPIC_PREFIX', 'escenia.'),
    ],
];
