<?php

declare(strict_types=1);

namespace App\Domain\Networking\Enums;

/**
 * Lifecycle of an attendee connection request. Guarded like the other state
 * machines: only the addressee moves it out of `pending`, and the end states
 * are terminal.
 */
enum ConnectionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';

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
            self::Pending => [self::Accepted, self::Declined],
            self::Accepted, self::Declined => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
