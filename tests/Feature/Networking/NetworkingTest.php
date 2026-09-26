<?php

declare(strict_types=1);

use App\Domain\Registration\Models\Attendee;

function attendeeUlid(string $email): string
{
    return Attendee::withoutGlobalScopes()->where('email', $email)->firstOrFail()->ulid;
}

function optIn(string $token): void
{
    test()->withHeaders(['X-Attendee-Token' => $token])
        ->putJson('/api/v1/attend/networking/preferences', ['opt_in' => true])
        ->assertOk();
}

it('lets an attendee request, and the other accept, a connection', function () {
    [, , $event] = makeWebinarHost();
    $alice = registerAttendee($event->ulid, 'Alice', 'alice@x.test');
    $bob = registerAttendee($event->ulid, 'Bob', 'bob@x.test');
    optIn($bob);
    $bobUlid = attendeeUlid('bob@x.test');

    $connId = $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson('/api/v1/attend/networking/connections', ['attendee_id' => $bobUlid, 'message' => 'Hola'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->json('data.id');

    $this->withHeaders(['X-Attendee-Token' => $bob])
        ->getJson('/api/v1/attend/networking/connections')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'pending');

    $this->withHeaders(['X-Attendee-Token' => $bob])
        ->postJson("/api/v1/attend/networking/connections/{$connId}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');
});

it('rejects a self-connection and a duplicate request', function () {
    [, , $event] = makeWebinarHost();
    $alice = registerAttendee($event->ulid, 'Alice', 'alice@x.test');
    $bob = registerAttendee($event->ulid, 'Bob', 'bob@x.test');
    optIn($bob);
    $aliceUlid = attendeeUlid('alice@x.test');
    $bobUlid = attendeeUlid('bob@x.test');

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson('/api/v1/attend/networking/connections', ['attendee_id' => $aliceUlid])
        ->assertStatus(422);

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson('/api/v1/attend/networking/connections', ['attendee_id' => $bobUlid])
        ->assertCreated();

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson('/api/v1/attend/networking/connections', ['attendee_id' => $bobUlid])
        ->assertStatus(409);
});

it('only lets the addressee accept a connection', function () {
    [, , $event] = makeWebinarHost();
    $alice = registerAttendee($event->ulid, 'Alice', 'alice@x.test');
    $bob = registerAttendee($event->ulid, 'Bob', 'bob@x.test');
    optIn($bob);
    $bobUlid = attendeeUlid('bob@x.test');

    $connId = $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson('/api/v1/attend/networking/connections', ['attendee_id' => $bobUlid])
        ->json('data.id');

    // The requester cannot accept their own request (scoped to the addressee).
    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson("/api/v1/attend/networking/connections/{$connId}/accept")
        ->assertNotFound();
});

it('proposes, accepts and cancels a 1:1 meeting', function () {
    [, , $event] = makeWebinarHost();
    $alice = registerAttendee($event->ulid, 'Alice', 'alice@x.test');
    $bob = registerAttendee($event->ulid, 'Bob', 'bob@x.test');
    optIn($bob);
    $bobUlid = attendeeUlid('bob@x.test');

    $meetingId = $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson('/api/v1/attend/networking/meetings', [
            'attendee_id' => $bobUlid,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'duration_minutes' => 30,
            'topic' => 'Coffee',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'proposed')
        ->json('data.id');

    $this->withHeaders(['X-Attendee-Token' => $bob])
        ->postJson("/api/v1/attend/networking/meetings/{$meetingId}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson("/api/v1/attend/networking/meetings/{$meetingId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'canceled');

    $this->withHeaders(['X-Attendee-Token' => $bob])
        ->postJson("/api/v1/attend/networking/meetings/{$meetingId}/accept")
        ->assertStatus(422);
});

it('lists only opted-in attendees in the directory with connection status', function () {
    [, , $event] = makeWebinarHost();
    $alice = registerAttendee($event->ulid, 'Alice', 'alice@x.test');
    $bob = registerAttendee($event->ulid, 'Bob', 'bob@x.test');

    // Before Bob opts in, the directory is empty.
    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->getJson('/api/v1/attend/networking/directory')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    optIn($bob);

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->getJson('/api/v1/attend/networking/directory')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Bob')
        ->assertJsonPath('data.0.connection_status', 'none');
});

it('rejects a connection request to an attendee who has not opted in', function () {
    [, , $event] = makeWebinarHost();
    $alice = registerAttendee($event->ulid, 'Alice', 'alice@x.test');
    registerAttendee($event->ulid, 'Bob', 'bob@x.test'); // not opted in
    $bobUlid = attendeeUlid('bob@x.test');

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->postJson('/api/v1/attend/networking/connections', ['attendee_id' => $bobUlid])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'networking_unavailable');
});

it('toggles the networking opt-in preference', function () {
    [, , $event] = makeWebinarHost();
    $alice = registerAttendee($event->ulid, 'Alice', 'alice@x.test');

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->getJson('/api/v1/attend/networking/preferences')
        ->assertOk()
        ->assertJsonPath('data.opt_in', false);

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->putJson('/api/v1/attend/networking/preferences', ['opt_in' => true])
        ->assertOk()
        ->assertJsonPath('data.opt_in', true);

    $this->withHeaders(['X-Attendee-Token' => $alice])
        ->getJson('/api/v1/attend/networking/preferences')
        ->assertOk()
        ->assertJsonPath('data.opt_in', true);
});
