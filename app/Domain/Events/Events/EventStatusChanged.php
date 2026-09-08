<?php

declare(strict_types=1);

namespace App\Domain\Events\Events;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain event fired when an event's lifecycle status changes. Later phases
 * (broadcast, notifications, analytics) subscribe to this instead of coupling
 * to the transition logic directly.
 */
final class EventStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Event $event,
        public readonly EventStatus $from,
        public readonly EventStatus $to,
    ) {}
}
