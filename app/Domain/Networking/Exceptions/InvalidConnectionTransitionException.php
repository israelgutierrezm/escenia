<?php

declare(strict_types=1);

namespace App\Domain\Networking\Exceptions;

use App\Domain\Networking\Enums\ConnectionStatus;
use RuntimeException;

/**
 * Thrown when a connection status change is not allowed by the lifecycle (or was
 * already changed concurrently). Mapped to 422.
 */
final class InvalidConnectionTransitionException extends RuntimeException
{
    public function __construct(
        public readonly ConnectionStatus $from,
        public readonly ConnectionStatus $to,
    ) {
        parent::__construct("Cannot move a connection from [{$from->value}] to [{$to->value}].");
    }
}
