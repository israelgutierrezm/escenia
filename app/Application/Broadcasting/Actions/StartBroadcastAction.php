<?php

declare(strict_types=1);

namespace App\Application\Broadcasting\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Broadcasting\Enums\BroadcastDestinationStatus;
use App\Domain\Broadcasting\Enums\BroadcastHealth;
use App\Domain\Broadcasting\Enums\BroadcastStatus;
use App\Domain\Broadcasting\Events\BroadcastStarted;
use App\Domain\Broadcasting\Models\BroadcastDestination;
use App\Domain\Broadcasting\Models\BroadcastSession;
use App\Domain\Broadcasting\Models\StreamDestination;
use App\Domain\Identity\Models\User;
use App\Domain\Media\Contracts\MediaEgressProvider;
use App\Domain\Media\ValueObjects\EgressSpec;
use App\Domain\Media\ValueObjects\RoomHandle;
use App\Domain\Media\ValueObjects\StreamOutput;
use App\Domain\Studio\Models\StudioSession;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Starts a broadcast: records it as `starting`, provisions the composite egress
 * to the selected destinations (multistream) + optional recording, then flips
 * to `live`. Idempotent — returns the active broadcast if one already runs.
 */
final class StartBroadcastAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MediaEgressProvider $egress,
    ) {}

    /**
     * @param  list<string>  $destinationUlids
     */
    public function execute(StudioSession $session, User $actor, array $destinationUlids, bool $record): BroadcastSession
    {
        $active = BroadcastSession::query()
            ->where('studio_session_id', $session->getKey())
            ->whereIn('status', [BroadcastStatus::Starting->value, BroadcastStatus::Live->value])
            ->latest()
            ->first();

        if ($active !== null) {
            return $active;
        }

        $destinations = StreamDestination::query()
            ->whereIn('ulid', $destinationUlids)
            ->where('is_enabled', true)
            ->get();

        $broadcast = DB::transaction(function () use ($session, $actor, $record, $destinations): BroadcastSession {
            $broadcast = BroadcastSession::create([
                'tenant_id' => $session->tenant_id,
                'studio_session_id' => $session->getKey(),
                'status' => BroadcastStatus::Starting,
                'health' => BroadcastHealth::Unknown,
                'record' => $record,
                'started_by' => $actor->getKey(),
                'started_at' => now(),
            ]);

            foreach ($destinations as $destination) {
                BroadcastDestination::create([
                    'tenant_id' => $session->tenant_id,
                    'broadcast_session_id' => $broadcast->getKey(),
                    'stream_destination_id' => $destination->getKey(),
                    'status' => BroadcastDestinationStatus::Pending,
                ]);
            }

            return $broadcast;
        });

        try {
            $outputs = $destinations
                ->map(fn (StreamDestination $d): StreamOutput => new StreamOutput($d->url, $d->stream_key))
                ->values()
                ->all();

            $handle = $this->egress->startEgress(
                new RoomHandle($session->room_ref, $session->provider),
                new EgressSpec($outputs, $record),
            );
        } catch (Throwable $e) {
            $broadcast->update(['status' => BroadcastStatus::Failed, 'health' => BroadcastHealth::Failed]);

            throw $e;
        }

        DB::transaction(function () use ($broadcast, $actor, $handle, $session, $record): void {
            $broadcast->update([
                'status' => BroadcastStatus::Live,
                'health' => BroadcastHealth::Healthy,
                'egress_ref' => $handle->id,
            ]);

            $broadcast->destinations()->update(['status' => BroadcastDestinationStatus::Live->value]);

            BroadcastStarted::dispatch($broadcast);

            $this->audit->log('broadcast.started', actor: $actor, tenant: $session->tenant, auditable: $broadcast, context: [
                'record' => $record,
                'destinations' => $broadcast->destinations()->count(),
            ]);
        });

        return $broadcast->refresh();
    }
}
