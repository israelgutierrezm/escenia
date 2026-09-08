<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Application\Studio\DTOs\AdmitParticipantData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\Models\StudioParticipant;
use App\Domain\Studio\Models\StudioSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admits a participant into a session's green room and issues their media token.
 *
 * @phpstan-type AdmitResult array{participant: StudioParticipant, token: AccessToken}
 */
final class AdmitParticipantAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly IssueParticipantTokenAction $issueToken,
    ) {}

    /**
     * @return array{participant: StudioParticipant, token: AccessToken}
     */
    public function execute(StudioSession $session, User $actor, AdmitParticipantData $data): array
    {
        $participant = DB::transaction(function () use ($session, $actor, $data): StudioParticipant {
            $participant = StudioParticipant::create([
                'tenant_id' => $session->tenant_id,
                'studio_session_id' => $session->getKey(),
                'user_id' => $data->userId,
                'identity' => 'p_'.strtolower((string) Str::ulid()),
                'name' => $data->name,
                'role' => $data->role,
                'stage' => ParticipantStage::GreenRoom,
                'joined_at' => now(),
            ]);

            $this->audit->log('studio.participant.admitted', actor: $actor, tenant: $session->tenant, auditable: $participant, context: [
                'role' => $data->role->value,
            ]);

            return $participant;
        });

        return [
            'participant' => $participant,
            'token' => $this->issueToken->execute($participant),
        ];
    }
}
