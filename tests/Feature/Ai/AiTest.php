<?php

declare(strict_types=1);

use App\Application\Outbox\DispatchOutboxAction;
use App\Domain\Events\Models\Event;
use Laravel\Sanctum\Sanctum;

/**
 * A tenant owner with a recording whose transcript has been produced and indexed
 * into content chunks (the fake transcript has three segments).
 *
 * @return array{0: Event, 1: array<string, string>, 2: string}
 */
function indexedEvent(): array
{
    [$user, $tenant, $event] = makeEventOwner('AI Event');
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $recId = test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/recordings", ['content_type' => 'video/mp4'])
        ->json('data.recording.id');
    test()->withHeaders($headers)->postJson("/api/v1/recordings/{$recId}/complete", ['duration_ms' => 60000])->assertOk();

    // Transcribe (sync job → ready → writes transcript.ready to the outbox).
    test()->withHeaders($headers)->postJson("/api/v1/recordings/{$recId}/transcribe")->assertStatus(202);

    // Drain the outbox → IndexTranscriptHandler embeds + stores content chunks.
    app(DispatchOutboxAction::class)->execute();

    return [$event, $headers, $recId];
}

it('indexes a ready transcript into content chunks', function () {
    [$event] = indexedEvent();

    $this->assertDatabaseCount('content_chunks', 3);
    $this->assertDatabaseHas('content_chunks', ['event_id' => $event->getKey(), 'position' => 0]);
});

it('finds the most relevant chunk by meaning (semantic replay)', function () {
    [$event, $headers] = indexedEvent();

    $response = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/content/search?q=roadmap")
        ->assertOk();

    expect($response->json('data'))->not->toBeEmpty();
    // The "Today we cover the roadmap" segment should rank first.
    expect(strtolower((string) $response->json('data.0.text')))->toContain('roadmap');
    expect($response->json('data.0.score'))->toBeGreaterThan(0);
});

it('answers a question grounded in the transcript with citations (smart Q&A)', function () {
    [$event, $headers] = indexedEvent();

    $response = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/content/ask", ['question' => 'What is covered in the session?'])
        ->assertOk();

    expect($response->json('data.answer'))->not->toBeEmpty();
    expect($response->json('data.citations'))->not->toBeEmpty();
    expect($response->json('data.model'))->toBe('fake-1');
});

it('generates a summary via a queued job (content factory)', function () {
    [, $headers, $recId] = indexedEvent();

    $summaryId = $this->withHeaders($headers)
        ->postJson("/api/v1/recordings/{$recId}/summaries", ['kind' => 'summary'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');

    // Queue is sync in tests: the job has produced the summary.
    $this->withHeaders($headers)
        ->getJson("/api/v1/summaries/{$summaryId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.provider', 'fake')
        ->assertJsonPath('data.content', fn ($c) => ! empty($c));
});

it('proposes an event plan (event architect)', function () {
    [$event, $headers] = indexedEvent();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/ai/architect", ['brief' => 'A 30-minute webinar introducing our API'])
        ->assertOk()
        ->assertJsonPath('data.plan', fn ($p) => ! empty($p))
        ->assertJsonPath('data.model', 'fake-1');
});

it('requires auth and isolates AI search by tenant', function () {
    $this->getJson('/api/v1/events/any/content/search?q=x')->assertUnauthorized();

    [$event] = indexedEvent();
    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->getJson("/api/v1/events/{$event->ulid}/content/search?q=roadmap")
        ->assertNotFound();
});
