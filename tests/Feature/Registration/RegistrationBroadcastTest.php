<?php

declare(strict_types=1);

use App\Application\Registration\Events\AttendeeRegistered;
use Illuminate\Support\Facades\Event;

it('broadcasts a new registration with the running total', function () {
    [, , $event] = makeWebinarHost();

    Event::fake([AttendeeRegistered::class]);

    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Ava', 'email' => 'ava@example.com'])
        ->assertCreated();

    Event::assertDispatched(
        AttendeeRegistered::class,
        fn (AttendeeRegistered $e): bool => $e->eventUlid === $event->ulid && $e->total === 1,
    );
});

it('does not broadcast when the same person re-registers', function () {
    [, , $event] = makeWebinarHost();

    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Ava', 'email' => 'ava@example.com'])
        ->assertCreated();

    Event::fake([AttendeeRegistered::class]);

    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Ava', 'email' => 'ava@example.com'])
        ->assertCreated();

    Event::assertNotDispatched(AttendeeRegistered::class);
});
