<?php

declare(strict_types=1);

use App\Application\Studio\Actions\MoveParticipantAction;
use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\Exceptions\ParticipantStageConflictException;
use App\Domain\Studio\Models\StudioParticipant;
use Laravel\Sanctum\Sanctum;

/**
 * Starts the studio and admits a participant, returning the participant ULID.
 */
function admitParticipant(string $tenantUlid, string $eventUlid, string $role = 'host'): string
{
    $headers = ['X-Tenant-Id' => $tenantUlid];

    test()->withHeaders($headers)->postJson("/api/v1/events/{$eventUlid}/studio/start");

    return (string) test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/studio/participants", ['name' => 'Participant', 'role' => $role])
        ->json('data.participant.id');
}

it('moves a participant through green room -> backstage -> stage', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $participantId = admitParticipant($tenant->ulid, $event->ulid);

    $this->withHeaders($headers)->postJson("/api/v1/studio-participants/{$participantId}/move", ['stage' => 'backstage'])
        ->assertOk()->assertJsonPath('data.stage', 'backstage');

    $this->withHeaders($headers)->postJson("/api/v1/studio-participants/{$participantId}/move", ['stage' => 'stage'])
        ->assertOk()->assertJsonPath('data.stage', 'stage');
});

it('rejects an illegal stage move with 422', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);

    $participantId = admitParticipant($tenant->ulid, $event->ulid);

    // green_room -> stage skips backstage and is not allowed.
    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->postJson("/api/v1/studio-participants/{$participantId}/move", ['stage' => 'stage'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'invalid_stage_transition');
});

it('rejects a concurrent stale stage move with 409', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);

    $participantId = admitParticipant($tenant->ulid, $event->ulid);

    $participant = StudioParticipant::withoutGlobalScopes()->where('ulid', $participantId)->firstOrFail();
    $stale = StudioParticipant::withoutGlobalScopes()->where('ulid', $participantId)->firstOrFail();

    $action = app(MoveParticipantAction::class);
    $action->execute($participant, $user, ParticipantStage::Backstage);

    expect(fn () => $action->execute($stale, $user, ParticipantStage::Backstage))
        ->toThrow(ParticipantStageConflictException::class);
});
