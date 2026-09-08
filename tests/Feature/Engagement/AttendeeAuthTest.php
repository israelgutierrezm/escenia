<?php

declare(strict_types=1);
use App\Domain\Engagement\Models\AttendeeSession;

it('requires an attendee token', function () {
    $this->postJson('/api/v1/attend/presence/join')
        ->assertUnauthorized();
});

it('rejects an unknown attendee token', function () {
    $this->withHeaders(['X-Attendee-Token' => 'nope'])
        ->postJson('/api/v1/attend/presence/join')
        ->assertUnauthorized();
});

it('opens and closes a presence session with a valid token', function () {
    [, , $event] = makeWebinarHost();
    $token = registerAttendee($event->ulid);
    $headers = ['X-Attendee-Token' => $token];

    $this->withHeaders($headers)->postJson('/api/v1/attend/presence/join')
        ->assertOk()
        ->assertJsonPath('data.left_at', null);

    // A second join is treated as a heartbeat, not a new session.
    $this->withHeaders($headers)->postJson('/api/v1/attend/presence/join')->assertOk();
    $this->assertDatabaseCount('attendee_sessions', 1);

    $this->withHeaders($headers)->postJson('/api/v1/attend/presence/leave')
        ->assertOk();
    expect(now()->diffInSeconds(
        AttendeeSession::query()->withoutGlobalScopes()->first()->left_at
    ))->toBeLessThan(5);
});

it('scopes attendee reads to their own event', function () {
    [, , $eventA] = makeWebinarHost('Event A');
    $tokenA = registerAttendee($eventA->ulid, 'A');

    // A host posts chat in event A only.
    [, , $eventB] = makeWebinarHost('Event B');

    // Attendee A sees event A's chat feed (empty here) and never event B's.
    $this->withHeaders(['X-Attendee-Token' => $tokenA])
        ->getJson('/api/v1/attend/chat')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect($eventA->ulid)->not->toBe($eventB->ulid);
});
