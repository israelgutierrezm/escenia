<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Events;

use App\Domain\Broadcasting\Models\BroadcastSession;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Critical lifecycle event. Per ADR-007 this will be published via the Outbox
 * pattern once external consumers (analytics, notifications) exist.
 */
final class BroadcastStarted
{
    use Dispatchable;

    public function __construct(
        public readonly BroadcastSession $broadcast,
    ) {}
}
