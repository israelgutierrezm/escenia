<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

/**
 * The system events that can start an automation. These are published through
 * the transactional outbox (ADR-007) at their choke points, so automations
 * react to committed facts, at-least-once.
 */
enum TriggerEvent: string
{
    case RegistrationCompleted = 'registration.completed';
    case OrderPaid = 'order.paid';
    case EventEnded = 'event.ended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
