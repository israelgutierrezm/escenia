<?php

declare(strict_types=1);

namespace App\Domain\Gamification\Enums;

/**
 * Gamified actions and the points they award. Values are fixed for now
 * (configurable per event is future — see technical-debt).
 */
enum PointsAction: string
{
    case SessionRegistered = 'session_registered';
    case BoothVisited = 'booth_visited';

    public function points(): int
    {
        return match ($this) {
            self::SessionRegistered => 10,
            self::BoothVisited => 5,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $a): string => $a->value, self::cases());
    }
}
