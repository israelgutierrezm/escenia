<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Exceptions;

use RuntimeException;

/**
 * Thrown when a custom domain's ownership challenge could not be verified.
 * Mapped to HTTP 422.
 */
final class DomainVerificationFailedException extends RuntimeException
{
    public function __construct(string $detail = 'Domain ownership could not be verified.')
    {
        parent::__construct($detail);
    }
}
