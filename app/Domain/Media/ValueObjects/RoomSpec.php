<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

/**
 * Provider-agnostic description of a media room to provision. No SDK types leak
 * into the domain (ADR-008).
 */
final class RoomSpec
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly ?int $maxParticipants = null,
        public readonly array $metadata = [],
    ) {}
}
