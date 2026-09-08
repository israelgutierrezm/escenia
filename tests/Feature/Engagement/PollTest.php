<?php

declare(strict_types=1);
use Laravel\Sanctum\Sanctum;

/**
 * @return array{0: string, 1: array<string,string>, 2: string} [eventUlid, hostHeaders, pollId (draft)]
 */
function draftPoll(): array
{
    [, , $event, $headers] = makeWebinarHost();

    $pollId = test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/engagement/polls", [
            'question' => 'Favourite tool?',
            'options' => ['Laravel', 'Vue'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->json('data.id');

    return [$event->ulid, $headers, $pollId];
}

it('runs the poll lifecycle and tallies votes on an open poll', function () {
    [$eventUlid, $headers, $pollId] = draftPoll();
    $voter = ['X-Attendee-Token' => registerAttendee($eventUlid, 'Voter')];

    // Cannot vote while draft.
    $optionId = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$eventUlid}/engagement/polls")
        ->json('data.0.options.0.id');

    $this->withHeaders($voter)
        ->postJson("/api/v1/attend/polls/{$pollId}/vote", ['option_id' => $optionId])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'poll_not_open');

    // Open, then vote.
    $this->withHeaders($headers)->postJson("/api/v1/polls/{$pollId}/open")
        ->assertOk()->assertJsonPath('data.status', 'open');

    $this->withHeaders($voter)
        ->postJson("/api/v1/attend/polls/{$pollId}/vote", ['option_id' => $optionId])
        ->assertOk();

    // Close it.
    $this->withHeaders($headers)->postJson("/api/v1/polls/{$pollId}/close")
        ->assertOk()->assertJsonPath('data.status', 'closed');

    $tally = collect(
        $this->withHeaders($headers)->getJson("/api/v1/events/{$eventUlid}/engagement/polls")->json('data.0.options')
    )->firstWhere('id', $optionId);
    expect($tally['votes_count'])->toBe(1);
});

it('rejects an illegal transition', function () {
    [, $headers, $pollId] = draftPoll();

    // draft -> closed is not allowed.
    $this->withHeaders($headers)->postJson("/api/v1/polls/{$pollId}/close")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'invalid_poll_transition');
});

it('rejects a double vote with a conflict', function () {
    [$eventUlid, $headers, $pollId] = draftPoll();
    $this->withHeaders($headers)->postJson("/api/v1/polls/{$pollId}/open")->assertOk();

    $optionId = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$eventUlid}/engagement/polls")->json('data.0.options.0.id');
    $voter = ['X-Attendee-Token' => registerAttendee($eventUlid, 'Voter')];

    $this->withHeaders($voter)->postJson("/api/v1/attend/polls/{$pollId}/vote", ['option_id' => $optionId])->assertOk();
    $this->withHeaders($voter)->postJson("/api/v1/attend/polls/{$pollId}/vote", ['option_id' => $optionId])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'already_voted');

    $this->assertDatabaseCount('poll_votes', 1);
});

it('hides draft polls from attendees', function () {
    [$eventUlid, , $pollId] = draftPoll();
    $attendee = ['X-Attendee-Token' => registerAttendee($eventUlid)];

    $this->withHeaders($attendee)->getJson('/api/v1/attend/polls')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect($pollId)->toBeString();
});

it('requires auth to create a poll', function () {
    // The host poll route is behind auth:sanctum; rejected before any lookup.
    $this->postJson('/api/v1/events/any-ulid/engagement/polls', [
        'question' => 'Q', 'options' => ['a', 'b'],
    ])->assertUnauthorized();
});

it('isolates poll creation from other tenants', function () {
    [, , $event] = makeWebinarHost();

    // A different tenant's owner cannot resolve this event (isolated -> 404).
    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->postJson("/api/v1/events/{$event->ulid}/engagement/polls", ['question' => 'Q', 'options' => ['a', 'b']])
        ->assertNotFound();
});

it('validates poll creation requires at least two options', function () {
    [, , $event, $headers] = makeWebinarHost();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/engagement/polls", ['question' => 'Q', 'options' => ['only one']])
        ->assertStatus(422);
});
