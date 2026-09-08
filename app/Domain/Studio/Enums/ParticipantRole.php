<?php

declare(strict_types=1);

namespace App\Domain\Studio\Enums;

enum ParticipantRole: string
{
    case Host = 'host';
    case Producer = 'producer';
    case Speaker = 'speaker';
    case Guest = 'guest';

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
