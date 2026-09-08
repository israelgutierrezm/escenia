<?php

declare(strict_types=1);
use Laravel\Sanctum\Sanctum;

it('lets attendees ask, upvote, and the host answer', function () {
    [, , $event, $headers] = makeWebinarHost();
    $asker = ['X-Attendee-Token' => registerAttendee($event->ulid, 'Asker')];
    $voter = ['X-Attendee-Token' => registerAttendee($event->ulid, 'Voter')];

    $questionId = $this->withHeaders($asker)
        ->postJson('/api/v1/attend/questions', ['body' => 'Will there be a recording?'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.votes_count', 0)
        ->json('data.id');

    $this->withHeaders($voter)
        ->postJson("/api/v1/attend/questions/{$questionId}/vote")
        ->assertOk()
        ->assertJsonPath('data.votes_count', 1);

    // The host answers it.
    $this->withHeaders($headers)
        ->postJson("/api/v1/questions/{$questionId}/answer", ['answer' => 'Yes, sent afterwards.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'answered')
        ->assertJsonPath('data.answer', 'Yes, sent afterwards.');
});

it('counts each attendee upvote at most once', function () {
    [, , $event] = makeWebinarHost();
    $asker = ['X-Attendee-Token' => registerAttendee($event->ulid, 'Asker')];

    $questionId = $this->withHeaders($asker)
        ->postJson('/api/v1/attend/questions', ['body' => 'Repeated vote?'])
        ->json('data.id');

    $this->withHeaders($asker)->postJson("/api/v1/attend/questions/{$questionId}/vote")->assertOk();
    $this->withHeaders($asker)->postJson("/api/v1/attend/questions/{$questionId}/vote")
        ->assertOk()
        ->assertJsonPath('data.votes_count', 1);

    $this->assertDatabaseCount('question_votes', 1);
});

it('orders the host queue by votes', function () {
    [, , $event, $headers] = makeWebinarHost();
    $one = ['X-Attendee-Token' => registerAttendee($event->ulid, 'One')];
    $two = ['X-Attendee-Token' => registerAttendee($event->ulid, 'Two')];

    $this->withHeaders($one)->postJson('/api/v1/attend/questions', ['body' => 'Low interest'])->json('data.id');
    $popular = $this->withHeaders($two)->postJson('/api/v1/attend/questions', ['body' => 'Hot topic'])->json('data.id');

    $this->withHeaders($one)->postJson("/api/v1/attend/questions/{$popular}/vote")->assertOk();

    $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/engagement/questions")
        ->assertOk()
        ->assertJsonPath('data.0.id', $popular);
});

it('requires auth to answer', function () {
    // The answer route is behind auth:sanctum; rejected before any lookup.
    $this->postJson('/api/v1/questions/any-ulid/answer', ['answer' => 'x'])
        ->assertUnauthorized();
});

it('isolates answering from other tenants', function () {
    [, , $event] = makeWebinarHost();
    $asker = ['X-Attendee-Token' => registerAttendee($event->ulid, 'Asker')];
    $questionId = $this->withHeaders($asker)->postJson('/api/v1/attend/questions', ['body' => 'Q'])->json('data.id');

    // A different tenant's owner cannot resolve this question (isolated -> 404).
    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->postJson("/api/v1/questions/{$questionId}/answer", ['answer' => 'x'])
        ->assertNotFound();
});
