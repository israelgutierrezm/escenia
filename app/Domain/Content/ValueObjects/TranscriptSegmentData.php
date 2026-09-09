<?php

declare(strict_types=1);

namespace App\Domain\Content\ValueObjects;

final class TranscriptSegmentData
{
    public function __construct(
        public readonly int $startMs,
        public readonly int $endMs,
        public readonly ?string $speaker,
        public readonly string $text,
    ) {}
}
