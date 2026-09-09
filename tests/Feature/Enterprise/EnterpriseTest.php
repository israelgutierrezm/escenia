<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, string>  $headers
 */
function createSession(string $eventUlid, array $headers, ?int $capacity = null): string
{
    $id = test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/sessions", ['title' => 'Keynote'])
        ->assertCreated()
        ->json('data.id');

    if ($capacity !== null) {
        test()->withHeaders($headers)
            ->patchJson("/api/v1/events/{$eventUlid}/sessions/{$id}/agenda", ['capacity' => $capacity])
            ->assertOk();
    }

    return $id;
}

/**
 * @param  array<string, string>  $headers
 */
function createBooth(string $eventUlid, array $headers): string
{
    $sponsor = test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/sponsors", ['name' => 'Acme', 'tier' => 'gold'])
        ->assertCreated()
        ->json('data.id');

    return test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/booths", ['sponsor' => $sponsor, 'name' => 'Acme Booth'])
        ->assertCreated()
        ->json('data.id');
}

it('builds a multi-session agenda with tracks and lets attendees register', function () {
    [, , $event, $headers] = makeWebinarHost();

    $track = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/tracks", ['name' => 'Product', 'color' => '#f00'])
        ->assertCreated()
        ->json('data.id');

    $sessionId = createSession($event->ulid, $headers);
    $this->withHeaders($headers)
        ->patchJson("/api/v1/events/{$event->ulid}/sessions/{$sessionId}/agenda", ['track' => $track, 'room' => 'Hall A', 'capacity' => 100])
        ->assertOk()
        ->assertJsonPath('data.room', 'Hall A')
        ->assertJsonPath('data.track', $track);

    $token = registerAttendee($event->ulid);

    $this->withHeaders(['X-Attendee-Token' => $token])->getJson('/api/v1/attend/agenda')
        ->assertOk()
        ->assertJsonPath('data.0.id', $sessionId);

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson("/api/v1/attend/agenda/{$sessionId}/register")
        ->assertCreated()
        ->assertJsonPath('data.registered_count', 1);

    $this->withHeaders(['X-Attendee-Token' => $token])->getJson('/api/v1/attend/agenda/mine')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Registering is idempotent (no double count).
    $this->withHeaders(['X-Attendee-Token' => $token])->postJson("/api/v1/attend/agenda/{$sessionId}/register")->assertCreated();
    $this->assertDatabaseHas('event_sessions', ['ulid' => $sessionId, 'registered_count' => 1]);
});

it('enforces session capacity', function () {
    [, , $event, $headers] = makeWebinarHost();
    $sessionId = createSession($event->ulid, $headers, capacity: 1);

    $first = registerAttendee($event->ulid, 'First');
    $second = registerAttendee($event->ulid, 'Second');

    $this->withHeaders(['X-Attendee-Token' => $first])->postJson("/api/v1/attend/agenda/{$sessionId}/register")->assertCreated();
    $this->withHeaders(['X-Attendee-Token' => $second])
        ->postJson("/api/v1/attend/agenda/{$sessionId}/register")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'session_full');
});

it('lets attendees unregister and frees the slot', function () {
    [, , $event, $headers] = makeWebinarHost();
    $sessionId = createSession($event->ulid, $headers, capacity: 1);
    $token = registerAttendee($event->ulid);

    $this->withHeaders(['X-Attendee-Token' => $token])->postJson("/api/v1/attend/agenda/{$sessionId}/register")->assertCreated();
    $this->withHeaders(['X-Attendee-Token' => $token])->deleteJson("/api/v1/attend/agenda/{$sessionId}/register")->assertOk();

    $this->assertDatabaseHas('event_sessions', ['ulid' => $sessionId, 'registered_count' => 0]);
});

it('captures a lead when an attendee visits a booth', function () {
    [, , $event, $headers] = makeWebinarHost();
    $boothId = createBooth($event->ulid, $headers);
    $token = registerAttendee($event->ulid, 'Lead Person');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson("/api/v1/attend/expo/booths/{$boothId}/visit", ['note' => 'Interested in the API'])
        ->assertCreated()
        ->assertJsonPath('data.leads_count', 1);

    // Idempotent visit.
    $this->withHeaders(['X-Attendee-Token' => $token])->postJson("/api/v1/attend/expo/booths/{$boothId}/visit")->assertCreated();
    $this->assertDatabaseCount('booth_leads', 1);

    $this->withHeaders($headers)->getJson("/api/v1/events/{$event->ulid}/leads")
        ->assertOk()
        ->assertJsonPath('data.0.attendee.name', 'Lead Person');
});

it('awards points and ranks attendees on the leaderboard', function () {
    [, , $event, $headers] = makeWebinarHost();
    $sessionId = createSession($event->ulid, $headers);
    $boothId = createBooth($event->ulid, $headers);
    $token = registerAttendee($event->ulid, 'Champion');

    $this->withHeaders(['X-Attendee-Token' => $token])->postJson("/api/v1/attend/agenda/{$sessionId}/register")->assertCreated();
    $this->withHeaders(['X-Attendee-Token' => $token])->postJson("/api/v1/attend/expo/booths/{$boothId}/visit")->assertCreated();

    // 10 (session) + 5 (booth) = 15.
    $this->withHeaders(['X-Attendee-Token' => $token])->getJson('/api/v1/attend/gamification')
        ->assertOk()
        ->assertJsonPath('data.points', 15)
        ->assertJsonPath('data.leaderboard.0.name', 'Champion')
        ->assertJsonPath('data.leaderboard.0.points', 15);

    $this->withHeaders($headers)->getJson("/api/v1/events/{$event->ulid}/leaderboard")
        ->assertOk()
        ->assertJsonPath('data.0.points', 15);
});

it('requires auth for host management and isolates by tenant', function () {
    $this->postJson('/api/v1/events/any/tracks', ['name' => 'x'])->assertUnauthorized();

    [, , $event] = makeWebinarHost();
    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->postJson("/api/v1/events/{$event->ulid}/tracks", ['name' => 'x'])
        ->assertNotFound();
});
