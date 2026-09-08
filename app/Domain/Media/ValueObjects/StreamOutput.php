<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

/**
 * A single egress destination: where to push the composited stream. Provider-
 * agnostic; the stream key is a secret and is never logged.
 */
final class StreamOutput
{
    public function __construct(
        public readonly string $url,
        public readonly string $streamKey,
    ) {}
}
