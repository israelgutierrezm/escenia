<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Events\SceneTaken;
use App\Domain\Production\Models\Scene;
use App\Domain\Studio\Models\Studio;

/**
 * Takes a scene to program (on-air) and emits a domain event for realtime.
 */
final class TakeSceneAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Studio $studio, User $actor, Scene $scene): Studio
    {
        $studio->update(['program_scene_id' => $scene->getKey()]);

        SceneTaken::dispatch($studio, $scene);

        $this->audit->log('production.take', actor: $actor, tenant: $studio->tenant, auditable: $studio, context: [
            'scene' => $scene->ulid,
        ]);

        return $studio;
    }
}
