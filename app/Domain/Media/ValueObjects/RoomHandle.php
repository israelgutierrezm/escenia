<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

/**
 * An opaque reference to a provisioned media room. `name` is the provider room
 * identifier; `provider` records which media provider owns it.
 */
final class RoomHandle
{
    public function __construct(
        public readonly string $name,
        public readonly string $provider,
    ) {}
}
