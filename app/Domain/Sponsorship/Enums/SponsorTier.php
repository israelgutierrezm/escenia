<?php

declare(strict_types=1);

namespace App\Domain\Sponsorship\Enums;

enum SponsorTier: string
{
    case Platinum = 'platinum';
    case Gold = 'gold';
    case Silver = 'silver';
    case Bronze = 'bronze';
    case Community = 'community';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
