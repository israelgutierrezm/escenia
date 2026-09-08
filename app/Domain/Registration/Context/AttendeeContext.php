<?php

declare(strict_types=1);

namespace App\Domain\Registration\Context;

use App\Domain\Registration\Models\Attendee;
use RuntimeException;

/**
 * Request-scoped holder of the attendee authenticated for the current request.
 *
 * The attendee is resolved server-side from the join token (see ResolveAttendee
 * middleware): the token is the credential, and only its hash is stored. Bound
 * as a scoped singleton so it resets per request (Octane-safe).
 */
class AttendeeContext
{
    private ?Attendee $attendee = null;

    public function setAttendee(Attendee $attendee): void
    {
        $this->attendee = $attendee;
    }

    public function attendee(): ?Attendee
    {
        return $this->attendee;
    }

    public function hasAttendee(): bool
    {
        return $this->attendee !== null;
    }

    public function attendeeOrFail(): Attendee
    {
        if ($this->attendee === null) {
            throw new RuntimeException('No attendee resolved for the current request.');
        }

        return $this->attendee;
    }
}
