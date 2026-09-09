<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use RuntimeException;

/**
 * Thrown when a clip's in/out range is invalid (end must be after start).
 * Mapped to HTTP 422.
 */
final class InvalidClipRangeException extends RuntimeException
{
    public function __construct(string $message = 'A clip must end after it starts.')
    {
        parent::__construct($message);
    }
}
