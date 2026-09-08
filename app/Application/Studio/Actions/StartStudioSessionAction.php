<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Media\ValueObjects\RoomSpec;
use App\Domain\Studio\Enums\StudioSessionStatus;
use App\Domain\Studio\Enums\StudioStatus;
use App\Domain\Studio\Events\StudioSessionStarted;
use App\Domain\Studio\Models\Studio;
use App\Domain\Studio\Models\StudioSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starts a live studio session: provisions a media room via the provider and
 * records the session. Idempotent — returns the existing live session if any.
 */
final class StartStudioSessionAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MediaProviderContract $media,
    ) {}

    public function execute(Studio $studio, User $actor): StudioSession
    {
        $current = $studio->currentSession();

        if ($current !== null) {
            return $current;
        }

        $room = $this->media->provisionRoom(new RoomSpec(
            name: 'st_'.strtolower((string) Str::ulid()),
            metadata: ['studio' => $studio->ulid],
        ));

        return DB::transaction(function () use ($studio, $actor, $room): StudioSession {
            $session = StudioSession::create([
                'tenant_id' => $studio->tenant_id,
                'studio_id' => $studio->getKey(),
                'status' => StudioSessionStatus::Live,
                'room_ref' => $room->name,
                'provider' => $this->media->name(),
                'started_by' => $actor->getKey(),
                'started_at' => now(),
            ]);

            $studio->update(['status' => StudioStatus::Live]);

            StudioSessionStarted::dispatch($session);

            $this->audit->log('studio.session.started', actor: $actor, tenant: $studio->tenant, auditable: $session, context: [
                'room' => $room->name,
            ]);

            return $session;
        });
    }
}
