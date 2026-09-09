<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Exceptions;

use RuntimeException;

/**
 * Thrown when an SSO callback cannot be verified or the connection is inactive.
 * Mapped to HTTP 401. The message stays generic — never leak which part failed.
 */
final class SsoAuthenticationException extends RuntimeException
{
    public function __construct(string $message = 'SSO authentication failed.')
    {
        parent::__construct($message);
    }
}
