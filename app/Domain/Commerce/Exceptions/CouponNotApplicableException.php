<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Exceptions;

use RuntimeException;

/**
 * Thrown when a coupon code cannot be applied at checkout — unknown, inactive,
 * outside its window, or fully redeemed. Mapped to HTTP 422.
 */
final class CouponNotApplicableException extends RuntimeException
{
    public function __construct(string $message = 'This coupon cannot be applied.')
    {
        parent::__construct($message);
    }
}
