<?php

declare(strict_types=1);

it('lets the host add resources and attendees download them idempotently', function () {
    [, , $event, $headers] = makeWebinarHost();
    $attendee = ['X-Attendee-Token' => registerAttendee($event->ulid)];

    $resourceId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/engagement/resources", [
            'title' => 'Slides',
            'url' => 'https://example.com/slides.pdf',
        ])
        ->assertCreated()
        ->assertJsonPath('data.downloads_count', 0)
        ->json('data.id');

    $this->withHeaders($attendee)->getJson('/api/v1/attend/resources')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Slides');

    $this->withHeaders($attendee)->postJson("/api/v1/attend/resources/{$resourceId}/download")
        ->assertOk()
        ->assertJsonPath('data.downloads_count', 1);

    // Downloading again does not inflate the counter.
    $this->withHeaders($attendee)->postJson("/api/v1/attend/resources/{$resourceId}/download")
        ->assertOk()
        ->assertJsonPath('data.downloads_count', 1);

    $this->assertDatabaseCount('resource_downloads', 1);
});

it('validates the resource url', function () {
    [, , $event, $headers] = makeWebinarHost();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/engagement/resources", ['title' => 'Bad', 'url' => 'not-a-url'])
        ->assertStatus(422);
});
