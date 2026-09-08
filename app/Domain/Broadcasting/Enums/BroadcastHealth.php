<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Enums;

enum BroadcastHealth: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $health): string => $health->value, self::cases());
    }
}
