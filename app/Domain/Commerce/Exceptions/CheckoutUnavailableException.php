<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown when a checkout cannot proceed: a ticket is inactive, outside its sales
 * window, or sold out. Mapped to HTTP 422.
 */
final class CheckoutUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'This ticket is not available for purchase.')
    {
        parent::__construct($message);
    }
}
