<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use App\Domain\Commerce\Enums\OrderStatus;
use RuntimeException;

/**
 * Thrown when an order transition loses an optimistic-locking race (e.g. a
 * duplicate webhook). Mapped to HTTP 409.
 */
final class OrderTransitionConflictException extends RuntimeException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
    ) {
        parent::__construct(
            "The order changed state concurrently; could not transition from [{$from->value}] to [{$to->value}]."
        );
    }
}
