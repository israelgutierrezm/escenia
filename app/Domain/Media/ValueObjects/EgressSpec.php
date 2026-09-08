<?php

declare(strict_types=1);

namespace App\Domain\Media\ValueObjects;

/**
 * What to egress from a room: the set of stream outputs (multistream) and
 * whether to also record to a file.
 */
final class EgressSpec
{
    /**
     * @param  list<StreamOutput>  $outputs
     */
    public function __construct(
        public readonly array $outputs,
        public readonly bool $record = false,
    ) {}
}
