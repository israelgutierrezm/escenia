<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Models\Scene;
use App\Domain\Studio\Models\Studio;

/**
 * Stages a scene in preview (off-air).
 */
final class SetPreviewSceneAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Studio $studio, User $actor, Scene $scene): Studio
    {
        $studio->update(['preview_scene_id' => $scene->getKey()]);

        $this->audit->log('production.preview', actor: $actor, tenant: $studio->tenant, auditable: $studio, context: [
            'scene' => $scene->ulid,
        ]);

        return $studio;
    }
}
