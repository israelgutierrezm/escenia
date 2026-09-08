<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Media\Contracts\MediaProviderContract;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\Enums\StudioSessionStatus;
use App\Domain\Studio\Enums\StudioStatus;
use App\Domain\Studio\Events\StudioSessionEnded;
use App\Domain\Studio\Models\StudioSession;
use Illuminate\Support\Facades\DB;

/**
 * Ends a live studio session: records it, evicts remaining participants and
 * closes the media room (best-effort, after commit).
 */
final class EndStudioSessionAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MediaProviderContract $media,
    ) {}

    public function execute(StudioSession $session, User $actor): StudioSession
    {
        DB::transaction(function () use ($session, $actor): void {
            $session->update([
                'status' => StudioSessionStatus::Ended,
                'ended_at' => now(),
            ]);

            $session->participants()
                ->where('stage', '!=', ParticipantStage::Left->value)
                ->update(['stage' => ParticipantStage::Left->value, 'left_at' => now()]);

            $session->studio->update(['status' => StudioStatus::Idle]);

            StudioSessionEnded::dispatch($session);

            $this->audit->log('studio.session.ended', actor: $actor, tenant: $session->tenant, auditable: $session);
        });

        // External cleanup outside the transaction; failures are logged, not fatal.
        $this->media->closeRoom(new RoomHandle($session->room_ref, $this->media->name()));

        return $session;
    }
}
