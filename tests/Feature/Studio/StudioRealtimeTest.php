<?php

declare(strict_types=1);

use App\Application\Studio\Events\StudioParticipantActivity;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('broadcasts participant activity when admitting and moving', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();

    // Fake only the broadcast event so the actions otherwise run normally.
    Event::fake([StudioParticipantActivity::class]);

    $admit = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/participants", ['name' => 'Ada', 'role' => 'host'])
        ->assertCreated();
    $participantId = $admit->json('data.participant.id');

    Event::assertDispatched(
        StudioParticipantActivity::class,
        fn (StudioParticipantActivity $e): bool => $e->action === 'joined'
            && $e->eventUlid === $event->ulid
            && $e->id === $participantId
            && $e->stage === 'green_room',
    );

    $this->withHeaders($headers)
        ->postJson("/api/v1/studio-participants/{$participantId}/move", ['stage' => 'backstage'])
        ->assertOk();

    Event::assertDispatched(
        StudioParticipantActivity::class,
        fn (StudioParticipantActivity $e): bool => $e->action === 'moved'
            && $e->id === $participantId
            && $e->stage === 'backstage',
    );
});

it('broadcasts a leave as a left activity', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();
    $participantId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/participants", ['name' => 'Ada', 'role' => 'host'])
        ->json('data.participant.id');

    Event::fake([StudioParticipantActivity::class]);

    $this->withHeaders($headers)
        ->postJson("/api/v1/studio-participants/{$participantId}/move", ['stage' => 'left'])
        ->assertOk();

    Event::assertDispatched(
        StudioParticipantActivity::class,
        fn (StudioParticipantActivity $e): bool => $e->action === 'left' && $e->id === $participantId,
    );
});
