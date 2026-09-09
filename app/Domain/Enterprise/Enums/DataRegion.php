<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Enums;

/**
 * Where a tenant's data is meant to reside. This records intent and drives
 * future residency enforcement (region pinning); it is not enforced at the
 * storage layer yet (see technical debt).
 */
enum DataRegion: string
{
    case Us = 'us';
    case Eu = 'eu';
    case Ap = 'ap';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::cases());
    }
}
