<?php

declare(strict_types=1);

namespace App\Application\Broadcasting\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Broadcasting\Enums\BroadcastStatus;
use App\Domain\Broadcasting\Events\BroadcastEnded;
use App\Domain\Broadcasting\Exceptions\BroadcastTransitionConflictException;
use App\Domain\Broadcasting\Exceptions\InvalidBroadcastTransitionException;
use App\Domain\Broadcasting\Models\BroadcastSession;
use App\Domain\Identity\Models\User;
use App\Domain\Media\Contracts\MediaEgressProvider;
use App\Domain\Media\ValueObjects\EgressHandle;
use Illuminate\Support\Facades\DB;

/**
 * Ends a broadcast with a guarded, optimistically-locked transition, then stops
 * the egress (best-effort, after commit).
 */
final class StopBroadcastAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MediaEgressProvider $egress,
    ) {}

    public function execute(BroadcastSession $broadcast, User $actor): BroadcastSession
    {
        $from = $broadcast->status;

        if (! $from->canTransitionTo(BroadcastStatus::Ended)) {
            throw new InvalidBroadcastTransitionException($from, BroadcastStatus::Ended);
        }

        $result = DB::transaction(function () use ($broadcast, $actor, $from): BroadcastSession {
            $applied = BroadcastSession::query()
                ->whereKey($broadcast->getKey())
                ->where('status', $from->value)
                ->update(['status' => BroadcastStatus::Ended->value, 'ended_at' => now()]);

            if ($applied === 0) {
                throw new BroadcastTransitionConflictException($from, BroadcastStatus::Ended);
            }

            $broadcast->refresh();

            BroadcastEnded::dispatch($broadcast);

            $this->audit->log('broadcast.ended', actor: $actor, tenant: $broadcast->tenant, auditable: $broadcast);

            return $broadcast;
        });

        if ($result->egress_ref !== null) {
            $this->egress->stopEgress(new EgressHandle($result->egress_ref, 'media'));
        }

        return $result;
    }
}
