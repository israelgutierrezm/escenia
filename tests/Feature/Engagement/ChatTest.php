<?php

declare(strict_types=1);

it('lets an attendee post chat and the host read it', function () {
    [, , $event, $headers] = makeWebinarHost();
    $token = registerAttendee($event->ulid, 'Chatty');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/chat', ['body' => 'Hello everyone'])
        ->assertCreated()
        ->assertJsonPath('data.author_name', 'Chatty')
        ->assertJsonPath('data.is_host', false);

    $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/engagement/chat")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Hello everyone');
});

it('lets the host post chat flagged as host', function () {
    [$user, , $event, $headers] = makeWebinarHost();
    $token = registerAttendee($event->ulid);

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/engagement/chat", ['body' => 'Welcome!'])
        ->assertCreated()
        ->assertJsonPath('data.is_host', true)
        ->assertJsonPath('data.author_name', $user->name);

    // The attendee sees the host message in their feed.
    $this->withHeaders(['X-Attendee-Token' => $token])
        ->getJson('/api/v1/attend/chat')
        ->assertOk()
        ->assertJsonPath('data.0.is_host', true);
});

it('validates chat body', function () {
    [, , $event] = makeWebinarHost();
    $token = registerAttendee($event->ulid);

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/chat', ['body' => ''])
        ->assertStatus(422);
});
