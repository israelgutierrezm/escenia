<?php

declare(strict_types=1);

namespace App\Infrastructure\Production;

use App\Domain\Production\Contracts\SceneDefinitionMigrator;
use App\Domain\Production\SceneSchema;

/**
 * Only schema 1.0 exists today, so migration is effectively a normalization
 * (stamp the current schema version). Future incompatible versions add ordered
 * upgrade steps here (ADR-006), keyed off the incoming schema_version.
 */
final class DefaultSceneDefinitionMigrator implements SceneDefinitionMigrator
{
    public function migrate(array $definition): array
    {
        // Placeholder for future upgrade steps, e.g.:
        //   if (($definition['schema_version'] ?? '1.0') === '1.0') { ...to 2.0... }

        $definition['schema_version'] = SceneSchema::CURRENT_VERSION;

        return $definition;
    }
}
