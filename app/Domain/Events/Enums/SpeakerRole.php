<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum SpeakerRole: string
{
    case Host = 'host';
    case Speaker = 'speaker';
    case Moderator = 'moderator';
    case Panelist = 'panelist';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
