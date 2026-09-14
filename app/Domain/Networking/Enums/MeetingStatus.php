<?php

declare(strict_types=1);

namespace App\Domain\Networking\Enums;

/**
 * Lifecycle of a 1:1 meeting. `proposed` can be accepted/declined by the invitee
 * or canceled by either party; an `accepted` meeting can still be canceled.
 */
enum MeetingStatus: string
{
    case Proposed = 'proposed';
    case Accepted = 'accepted';
    case Declined = 'declined';
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
            self::Proposed => [self::Accepted, self::Declined, self::Canceled],
            self::Accepted => [self::Canceled],
            self::Declined, self::Canceled => [],
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
