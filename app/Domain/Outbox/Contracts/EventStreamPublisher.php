<?php

declare(strict_types=1);

namespace App\Domain\Outbox\Contracts;

/**
 * Publishes a domain event to an external stream (Kafka/Redpanda) so other
 * services or regions can consume it (ADR-031). Speaks in primitives — topic,
 * partition key, payload — so the domain never depends on a broker SDK
 * (ADR-008). The default implementation is a no-op: no infra before it is
 * needed (CLAUDE.md §3).
 */
interface EventStreamPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $topic, string $key, array $payload): void;
}
