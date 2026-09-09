<?php

declare(strict_types=1);

namespace App\Domain\Ai\Enums;

/**
 * The kind of AI artifact produced from a recording (Content Factory).
 */
enum SummaryKind: string
{
    case Summary = 'summary';
    case Chapters = 'chapters';
    case Highlights = 'highlights';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $k): string => $k->value, self::cases());
    }
}
