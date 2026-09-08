<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

/**
 * A participant's stable identity within a media room (unique per room).
 */
final class ParticipantIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}
