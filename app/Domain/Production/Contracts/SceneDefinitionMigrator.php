<?php

declare(strict_types=1);

namespace App\Domain\Production\Contracts;

/**
 * Upgrades a stored scene definition to the current schema version so old
 * designs keep opening after the Studio evolves (ADR-006).
 */
interface SceneDefinitionMigrator
{
    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function migrate(array $definition): array;
}
