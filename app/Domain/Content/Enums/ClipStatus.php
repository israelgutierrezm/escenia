<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum ClipStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
