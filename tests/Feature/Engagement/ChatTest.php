<?php

declare(strict_types=1);

use App\Application\Engagement\Events\ChatMessagePosted;
use Illuminate\Support\Facades\Event;

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

it('broadcasts a chat message on the event channel for real-time delivery', function () {
    Event::fake([ChatMessagePosted::class]);

    [, , $event] = makeWebinarHost();
    $token = registerAttendee($event->ulid, 'Chatty');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/chat', ['body' => 'Hola en directo'])
        ->assertCreated();

    Event::assertDispatched(ChatMessagePosted::class, function (ChatMessagePosted $e) use ($event): bool {
        return $e->eventUlid === $event->ulid
            && $e->body === 'Hola en directo'
            && $e->isHost === false
            && $e->broadcastOn()->name === "event.{$event->ulid}"
            && $e->broadcastAs() === 'chat.posted';
    });
});

it('broadcasts host chat flagged as host', function () {
    Event::fake([ChatMessagePosted::class]);

    [, , $event, $headers] = makeWebinarHost();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/engagement/chat", ['body' => 'Bienvenidos'])
        ->assertCreated();

    Event::assertDispatched(
        ChatMessagePosted::class,
        fn (ChatMessagePosted $e): bool => $e->isHost === true && $e->body === 'Bienvenidos',
    );
});

it('validates chat body', function () {
    [, , $event] = makeWebinarHost();
    $token = registerAttendee($event->ulid);

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/chat', ['body' => ''])
        ->assertStatus(422);
});
