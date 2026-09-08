<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Models\Scene;
use App\Domain\Production\Models\SceneVersion;
use App\Domain\Production\SceneSchema;
use App\Domain\Studio\Models\Studio;
use Illuminate\Support\Facades\DB;

/**
 * Creates a scene with an initial blank version at the current schema version.
 */
final class CreateSceneAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Studio $studio, User $actor, string $name): Scene
    {
        return DB::transaction(function () use ($studio, $actor, $name): Scene {
            $position = (int) Scene::query()->where('studio_id', $studio->getKey())->max('position') + 1;

            $scene = Scene::create([
                'tenant_id' => $studio->tenant_id,
                'studio_id' => $studio->getKey(),
                'name' => $name,
                'position' => $position,
            ]);

            SceneVersion::create([
                'tenant_id' => $studio->tenant_id,
                'scene_id' => $scene->getKey(),
                'version' => 1,
                'schema_version' => SceneSchema::CURRENT_VERSION,
                'definition' => SceneSchema::blank(),
                'is_current' => true,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->log('scene.created', actor: $actor, tenant: $studio->tenant, auditable: $scene, context: [
                'name' => $name,
            ]);

            return $scene;
        });
    }
}
