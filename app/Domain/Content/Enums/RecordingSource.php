<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum RecordingSource: string
{
    case Broadcast = 'broadcast';
    case Upload = 'upload';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
