<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Contracts\SceneDefinitionMigrator;
use App\Domain\Production\Models\Scene;
use App\Domain\Production\Models\SceneVersion;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new immutable version of a scene's definition. The incoming document
 * is normalized to the current schema by the migrator (ADR-006), and becomes
 * the current version.
 */
final class UpdateSceneDefinitionAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SceneDefinitionMigrator $migrator,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public function execute(Scene $scene, User $actor, array $definition): SceneVersion
    {
        $definition = $this->migrator->migrate($definition);

        return DB::transaction(function () use ($scene, $actor, $definition): SceneVersion {
            $nextVersion = (int) $scene->versions()->max('version') + 1;

            $scene->versions()->where('is_current', true)->update(['is_current' => false]);

            $version = SceneVersion::create([
                'tenant_id' => $scene->tenant_id,
                'scene_id' => $scene->getKey(),
                'version' => $nextVersion,
                'schema_version' => (string) $definition['schema_version'],
                'definition' => $definition,
                'is_current' => true,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log('scene.version.created', actor: $actor, tenant: $scene->tenant, auditable: $scene, context: [
                'version' => $nextVersion,
            ]);

            return $version;
        });
    }
}
