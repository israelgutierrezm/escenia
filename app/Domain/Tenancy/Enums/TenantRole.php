<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

/**
 * Tenant-scoped membership level. This is the source of truth for a user's
 * standing inside a tenant; it is mirrored to a Spatie role assignment scoped
 * by the tenant team so fine-grained permission checks resolve correctly
 * (see ADR-011).
 */
enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

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
