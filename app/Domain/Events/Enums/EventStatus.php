<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

/**
 * Lifecycle state of an event. Transitions are guarded: only the moves declared
 * in {@see allowedTransitions()} are legal (see ADR-017). The state machine is
 * enforced by TransitionEventAction, never by ad-hoc status writes.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Archived = 'archived';
    case Canceled = 'canceled';

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
            self::Draft => [self::Scheduled, self::Canceled],
            self::Scheduled => [self::Live, self::Draft, self::Canceled],
            self::Live => [self::Ended],
            self::Ended => [self::Archived],
            self::Archived, self::Canceled => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
