<?php

declare(strict_types=1);

namespace App\Domain\Networking\Exceptions;

use RuntimeException;

/**
 * Thrown when an attendee tries to connect with or meet themselves. Mapped to 422.
 */
final class SelfNetworkingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You cannot network with yourself.');
    }
}
