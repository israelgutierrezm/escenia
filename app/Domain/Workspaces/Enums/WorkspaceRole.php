<?php

declare(strict_types=1);

namespace App\Domain\Workspaces\Enums;

/**
 * Workspace-scoped role. Foundation implements only the Tenant and Workspace
 * authorization scopes; Event/Session scopes arrive with later phases
 * (see ADR-011).
 */
enum WorkspaceRole: string
{
    case Manager = 'manager';
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
