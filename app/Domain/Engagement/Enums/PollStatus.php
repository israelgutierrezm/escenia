<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Enums;

/**
 * Poll lifecycle: draft → open → closed. Guarded transitions enforced with
 * optimistic locking, like the event and broadcast state machines. A poll only
 * accepts votes while open.
 */
enum PollStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

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
            self::Draft => [self::Open],
            self::Open => [self::Closed],
            self::Closed => [],
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
