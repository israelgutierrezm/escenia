<?php

declare(strict_types=1);

namespace App\Domain\Content\ValueObjects;

final class TranscriptResult
{
    /**
     * @param  list<TranscriptSegmentData>  $segments
     */
    public function __construct(
        public readonly string $language,
        public readonly array $segments,
    ) {}
}
