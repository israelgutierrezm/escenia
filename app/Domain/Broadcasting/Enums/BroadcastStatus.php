<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Enums;

/**
 * Lifecycle of a broadcast. Guarded transitions (ADR-021), enforced with
 * optimistic locking like the event and participant state machines.
 */
enum BroadcastStatus: string
{
    case Idle = 'idle';
    case Starting = 'starting';
    case Live = 'live';
    case Ended = 'ended';
    case Failed = 'failed';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Idle => [self::Starting, self::Live],
            self::Starting => [self::Live, self::Failed],
            self::Live => [self::Ended, self::Failed],
            self::Ended, self::Failed => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
