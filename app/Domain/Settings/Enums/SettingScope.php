<?php

declare(strict_types=1);

namespace App\Domain\Settings\Enums;

/**
 * Where a setting may live. A stored row is either `system` or `tenant`; a
 * catalog definition may additionally be `both` (a tenant value overrides the
 * system value, which overrides the config/env default).
 */
enum SettingScope: string
{
    case System = 'system';
    case Tenant = 'tenant';
    case Both = 'both';

    public function allowsSystem(): bool
    {
        return $this === self::System || $this === self::Both;
    }

    public function allowsTenant(): bool
    {
        return $this === self::Tenant || $this === self::Both;
    }
}
