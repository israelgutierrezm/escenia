<?php

declare(strict_types=1);

namespace App\Domain\Outbox\Contracts;

use App\Domain\Outbox\Models\OutboxEvent;

/**
 * Handles a published outbox event. Because publication is at-least-once
 * (ADR-007), handlers MUST be idempotent — a replayed event must be a no-op.
 */
interface OutboxHandler
{
    public function handle(OutboxEvent $event): void;
}
