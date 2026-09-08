<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
