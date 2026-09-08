<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use App\Domain\Commerce\Enums\OrderStatus;
use RuntimeException;

/**
 * Thrown when an order lifecycle transition is not allowed. Mapped to HTTP 422.
 */
final class InvalidOrderTransitionException extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct("Cannot transition an order from [{$from->value}] to [{$to->value}].");
    }
}
