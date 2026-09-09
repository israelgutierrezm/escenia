<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

/**
 * The kind of an isolated recording track (ISO tracks). `composite` is the mixed
 * program; the others are per-source isolations for post-production.
 */
enum TrackKind: string
{
    case Composite = 'composite';
    case Screen = 'screen';
    case Camera = 'camera';
    case Audio = 'audio';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $k): string => $k->value, self::cases());
    }
}
