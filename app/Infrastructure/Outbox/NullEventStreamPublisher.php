<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Domain\Outbox\Contracts\EventStreamPublisher;

/**
 * The default publisher: does nothing. The outbox already delivers to internal
 * handlers; external fan-out stays off until a real consumer justifies it
 * (CLAUDE.md §3 — no infra before need).
 */
final class NullEventStreamPublisher implements EventStreamPublisher
{
    public function publish(string $topic, string $key, array $payload): void
    {
        // Intentionally empty.
    }
}
