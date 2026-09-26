<?php

declare(strict_types=1);

namespace App\Domain\Networking\Exceptions;

use RuntimeException;

/**
 * Thrown when the target attendee has not enabled networking, so they cannot be
 * sent a connection request or a meeting proposal. Mapped to 422.
 */
final class NetworkingUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This attendee is not accepting networking.');
    }
}
