<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

/**
 * Drive a full attendee engagement flow (join, chat, Q&A, poll, download) so the
 * analytics plane has something to report.
 *
 * @param  array<string, string>  $hostHeaders
 */
function driveEngagement(string $eventUlid, array $hostHeaders, string $attendeeToken): void
{
    $attendee = ['X-Attendee-Token' => $attendeeToken];

    test()->withHeaders($attendee)->postJson('/api/v1/attend/presence/join')->assertOk();
    test()->withHeaders($attendee)->postJson('/api/v1/attend/chat', ['body' => 'Hi'])->assertCreated();
    test()->withHeaders($attendee)->postJson('/api/v1/attend/chat', ['body' => 'Great talk'])->assertCreated();

    $questionId = test()->withHeaders($attendee)
        ->postJson('/api/v1/attend/questions', ['body' => 'Any slides?'])->json('data.id');
    test()->withHeaders($attendee)->postJson("/api/v1/attend/questions/{$questionId}/vote")->assertOk();

    $poll = test()->withHeaders($hostHeaders)
        ->postJson("/api/v1/events/{$eventUlid}/engagement/polls", ['question' => 'Rating?', 'options' => ['A', 'B']])
        ->json('data');
    test()->withHeaders($hostHeaders)->postJson("/api/v1/polls/{$poll['id']}/open")->assertOk();
    test()->withHeaders($attendee)
        ->postJson("/api/v1/attend/polls/{$poll['id']}/vote", ['option_id' => $poll['options'][0]['id']])
        ->assertOk();

    $resourceId = test()->withHeaders($hostHeaders)
        ->postJson("/api/v1/events/{$eventUlid}/engagement/resources", ['title' => 'Deck', 'url' => 'https://x.test/deck.pdf'])
        ->json('data.id');
    test()->withHeaders($attendee)->postJson("/api/v1/attend/resources/{$resourceId}/download")->assertOk();
}

it('summarises attendance and engagement for an event', function () {
    [, , $event, $headers] = makeWebinarHost();
    $token = registerAttendee($event->ulid, 'Ana');

    driveEngagement($event->ulid, $headers, $token);

    $summary = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/analytics/summary")
        ->assertOk()
        ->json('data');

    expect($summary['registrations'])->toBe(1)
        ->and($summary['registered_attendees'])->toBe(1)
        ->and($summary['attended_attendees'])->toBe(1)
        ->and($summary['attendance_rate'])->toEqual(1.0)
        ->and($summary['peak_concurrent'])->toBeGreaterThanOrEqual(1)
        ->and($summary['engagement']['chat_messages'])->toBe(2)
        ->and($summary['engagement']['questions_asked'])->toBe(1)
        ->and($summary['engagement']['question_votes'])->toBe(1)
        ->and($summary['engagement']['poll_votes'])->toBe(1)
        ->and($summary['engagement']['resource_downloads'])->toBe(1);
});

it('records a registration analytics event with attribution', function () {
    [, , $event] = makeWebinarHost();

    $this->postJson("/api/v1/events/{$event->ulid}/register", [
        'name' => 'Src',
        'email' => 'src@example.com',
        'attribution' => ['utm_source' => 'twitter', 'utm_campaign' => 'launch'],
    ])->assertCreated();

    $this->assertDatabaseHas('analytics_events', ['name' => 'registration.completed']);
});

it('breaks registrations and attendance down by source', function () {
    [, , $event, $headers] = makeWebinarHost();

    // From Twitter, and they attend.
    $twitter = $this->postJson("/api/v1/events/{$event->ulid}/register", [
        'name' => 'Tia', 'email' => 'tia@example.com', 'attribution' => ['utm_source' => 'twitter'],
    ])->json('data.token');
    $this->withHeaders(['X-Attendee-Token' => $twitter])->postJson('/api/v1/attend/presence/join')->assertOk();

    // Direct (no attribution), does not attend.
    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Dan', 'email' => 'dan@example.com'])
        ->assertCreated();

    $sources = collect(
        $this->withHeaders($headers)->getJson("/api/v1/events/{$event->ulid}/analytics/attribution")
            ->assertOk()->json('data.sources')
    )->keyBy('source');

    expect($sources['twitter']['registrations'])->toBe(1)
        ->and($sources['twitter']['attended'])->toBe(1)
        ->and($sources['direct']['registrations'])->toBe(1)
        ->and($sources['direct']['attended'])->toBe(0);
});

it('returns an attendance timeline once someone joins', function () {
    [, , $event, $headers] = makeWebinarHost();
    $token = registerAttendee($event->ulid);
    $this->withHeaders(['X-Attendee-Token' => $token])->postJson('/api/v1/attend/presence/join')->assertOk();

    $data = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/analytics/attendance")
        ->assertOk()
        ->json('data');

    expect($data['points'])->not->toBeEmpty()
        ->and($data['points'][0]['concurrent'])->toBeGreaterThanOrEqual(1);
});

it('requires auth to read analytics', function () {
    $this->getJson('/api/v1/events/any-ulid/analytics/summary')->assertUnauthorized();
});

it('isolates analytics from other tenants', function () {
    [, , $event] = makeWebinarHost();

    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->getJson("/api/v1/events/{$event->ulid}/analytics/summary")
        ->assertNotFound();
});
