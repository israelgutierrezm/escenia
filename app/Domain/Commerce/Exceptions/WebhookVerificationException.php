<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown when a payment webhook fails signature/authenticity verification.
 * Mapped to HTTP 403.
 */
final class WebhookVerificationException extends RuntimeException
{
    public function __construct(string $message = 'Webhook verification failed.')
    {
        parent::__construct($message);
    }
}
