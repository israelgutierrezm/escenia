<?php

declare(strict_types=1);

use App\Application\Outbox\DispatchOutboxAction;
use App\Domain\Events\Models\Event;
use Laravel\Sanctum\Sanctum;

/**
 * @return array{0: Event, 1: array<string, string>}
 */
function contentHost(string $title = 'Recorded Event'): array
{
    [$user, $tenant, $event] = makeEventOwner($title);
    Sanctum::actingAs($user);

    return [$event, ['X-Tenant-Id' => $tenant->ulid]];
}

/**
 * @param  array<string, string>  $headers
 */
function readyRecording(string $eventUlid, array $headers): string
{
    $recId = test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/recordings", ['content_type' => 'video/mp4'])
        ->assertCreated()
        ->json('data.recording.id');

    test()->withHeaders($headers)
        ->postJson("/api/v1/recordings/{$recId}/complete", ['duration_ms' => 60000, 'size_bytes' => 1048576, 'format' => 'mp4'])
        ->assertOk();

    return $recId;
}

it('issues a signed upload ticket and completes into a composite track', function () {
    [$event, $headers] = contentHost();

    $response = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/recordings", ['content_type' => 'video/mp4', 'title' => 'Keynote'])
        ->assertCreated();

    $response->assertJsonPath('data.recording.status', 'pending')
        ->assertJsonPath('data.recording.source', 'upload')
        ->assertJsonPath('data.upload.method', 'PUT');
    expect($response->json('data.upload.url'))->toContain('/upload/');
    expect($response->json('data.upload.key'))->toContain('recordings/');

    $recId = $response->json('data.recording.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/recordings/{$recId}/complete", ['duration_ms' => 60000, 'size_bytes' => 2048, 'format' => 'mp4'])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.duration_ms', 60000)
        ->assertJsonPath('data.tracks.0.kind', 'composite')
        ->assertJsonPath('data.playback_url', fn ($url) => str_contains((string) $url, '/play/'));
});

it('transcribes a recording via the fake provider (queued job)', function () {
    [$event, $headers] = contentHost();
    $recId = readyRecording($event->ulid, $headers);

    $transcriptId = $this->withHeaders($headers)
        ->postJson("/api/v1/recordings/{$recId}/transcribe", ['language' => 'en'])
        ->assertStatus(202)
        ->json('data.id');

    // Queue is sync in tests, so the job has produced the segments.
    $this->withHeaders($headers)
        ->getJson("/api/v1/transcripts/{$transcriptId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonCount(3, 'data.segments')
        ->assertJsonPath('data.segments.0.speaker', 'Host');
});

it('cuts a clip and rejects an invalid range', function () {
    [$event, $headers] = contentHost();
    $recId = readyRecording($event->ulid, $headers);

    $this->withHeaders($headers)
        ->postJson("/api/v1/recordings/{$recId}/clips", ['title' => 'Highlight', 'start_ms' => 5000, 'end_ms' => 12000])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', 'Highlight');

    $this->withHeaders($headers)
        ->postJson("/api/v1/recordings/{$recId}/clips", ['title' => 'Bad', 'start_ms' => 9000, 'end_ms' => 9000])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'invalid_clip_range');
});

it('registers a recording when a recorded broadcast ends (via outbox)', function () {
    [$event, $headers] = contentHost();

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();
    $dest = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/destinations", [
            'name' => 'YouTube', 'protocol' => 'rtmps', 'url' => 'rtmps://a/live', 'stream_key' => 'k',
        ])->json('data.id');
    $broadcastId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => [$dest], 'record' => true])
        ->json('data.id');
    $this->withHeaders($headers)->postJson("/api/v1/broadcasts/{$broadcastId}/stop")->assertOk();

    app(DispatchOutboxAction::class)->execute();

    $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/recordings")
        ->assertOk()
        ->assertJsonPath('data.0.source', 'broadcast')
        ->assertJsonPath('data.0.status', 'processing');
});

it('does not register a recording for a non-recorded broadcast', function () {
    [$event, $headers] = contentHost();

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();
    $dest = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/destinations", [
            'name' => 'YouTube', 'protocol' => 'rtmps', 'url' => 'rtmps://a/live', 'stream_key' => 'k',
        ])->json('data.id');
    $broadcastId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => [$dest], 'record' => false])
        ->json('data.id');
    $this->withHeaders($headers)->postJson("/api/v1/broadcasts/{$broadcastId}/stop")->assertOk();

    app(DispatchOutboxAction::class)->execute();

    $this->withHeaders($headers)->getJson("/api/v1/events/{$event->ulid}/recordings")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires auth and isolates recordings by tenant', function () {
    $this->postJson('/api/v1/events/any/recordings', ['content_type' => 'video/mp4'])->assertUnauthorized();

    [$event] = contentHost();
    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->postJson("/api/v1/events/{$event->ulid}/recordings", ['content_type' => 'video/mp4'])
        ->assertNotFound();
});
