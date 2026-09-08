<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

/**
 * An opaque reference to a running egress, used to stop it or query its health.
 */
final class EgressHandle
{
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
    ) {}
}
