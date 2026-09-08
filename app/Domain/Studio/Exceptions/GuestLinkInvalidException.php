<?php

declare(strict_types=1);

namespace App\Domain\Studio\Exceptions;

use RuntimeException;

/**
 * Thrown when a guest link cannot be redeemed (not found, expired, revoked or
 * out of uses). Mapped to HTTP 403 — the message is intentionally generic so it
 * does not reveal which condition failed.
 */
final class GuestLinkInvalidException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This guest link is no longer valid.');
    }
}
