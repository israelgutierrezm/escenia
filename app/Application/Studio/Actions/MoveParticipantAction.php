<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\Events\ParticipantStageChanged;
use App\Domain\Studio\Exceptions\InvalidParticipantStageTransitionException;
use App\Domain\Studio\Exceptions\ParticipantStageConflictException;
use App\Domain\Studio\Models\StudioParticipant;
use Illuminate\Support\Facades\DB;

/**
 * Moves a participant along the stage lifecycle with the same guarded,
 * optimistically-locked transition used for events (ADR-017 / ADR-019):
 *  - illegal moves -> InvalidParticipantStageTransitionException (422);
 *  - lost concurrency race -> ParticipantStageConflictException (409).
 */
final class MoveParticipantAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(StudioParticipant $participant, User $actor, ParticipantStage $target): StudioParticipant
    {
        $from = $participant->stage;

        if (! $from->canTransitionTo($target)) {
            throw new InvalidParticipantStageTransitionException($from, $target);
        }

        return DB::transaction(function () use ($participant, $actor, $from, $target): StudioParticipant {
            $updates = ['stage' => $target->value];

            if ($target->isInRoom() && $participant->joined_at === null) {
                $updates['joined_at'] = now();
            }

            if ($target === ParticipantStage::Left && $participant->left_at === null) {
                $updates['left_at'] = now();
            }

            // Compare-and-swap on the source stage.
            $applied = StudioParticipant::query()
                ->whereKey($participant->getKey())
                ->where('stage', $from->value)
                ->update($updates);

            if ($applied === 0) {
                throw new ParticipantStageConflictException($from, $target);
            }

            $participant->refresh();

            ParticipantStageChanged::dispatch($participant, $from, $target);

            $this->audit->log('studio.participant.moved', actor: $actor, tenant: $participant->tenant, auditable: $participant, context: [
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $participant;
        });
    }
}
