<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Studio\Enums\StudioStatus;
use App\Domain\Studio\Models\Studio;

/**
 * Returns the studio for an event, creating it (idle) on first use.
 */
final class EnsureStudioAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MediaProviderContract $media,
    ) {}

    public function execute(Event $event, User $actor): Studio
    {
        $existing = Studio::query()->where('event_id', $event->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        $studio = Studio::create([
            'tenant_id' => $event->tenant_id,
            'workspace_id' => $event->workspace_id,
            'event_id' => $event->getKey(),
            'name' => $event->title.' Studio',
            'status' => StudioStatus::Idle,
            'provider' => $this->media->name(),
        ]);

        $this->audit->log('studio.created', actor: $actor, tenant: $event->tenant, auditable: $studio);

        return $studio;
    }
}
