<?php

declare(strict_types=1);

use App\Application\Outbox\DispatchOutboxAction;
use App\Domain\Education\Models\Certificate;

/**
 * @param  array<string, string>  $headers
 */
function saveAssessment(string $eventUlid, array $headers, bool $published = true): void
{
    test()->withHeaders($headers)
        ->putJson("/api/v1/events/{$eventUlid}/education/assessment", [
            'title' => 'Final Quiz',
            'passing_score' => 50,
            'is_published' => $published,
            'questions' => [
                ['prompt' => 'What is 2 + 2?', 'type' => 'single_choice', 'points' => 1, 'options' => [
                    ['key' => 'a', 'label' => '3', 'correct' => false],
                    ['key' => 'b', 'label' => '4', 'correct' => true],
                ]],
            ],
        ])
        ->assertOk();
}

/**
 * @param  array<string, string>  $headers
 */
function setCompletionRule(string $eventUlid, array $headers, ?int $minWatch, bool $requireAssessment): void
{
    test()->withHeaders($headers)
        ->putJson("/api/v1/events/{$eventUlid}/education/completion-rule", [
            'min_watch_seconds' => $minWatch,
            'require_assessment' => $requireAssessment,
        ])
        ->assertOk();
}

it('never leaks the answer key to attendees', function () {
    [, , $event, $headers] = makeWebinarHost();
    saveAssessment($event->ulid, $headers);
    $token = registerAttendee($event->ulid);

    $response = $this->withHeaders(['X-Attendee-Token' => $token])
        ->getJson('/api/v1/attend/assessment')
        ->assertOk();

    expect($response->json('data.questions.0.options.0'))->toHaveKeys(['key', 'label']);
    expect($response->json('data.questions.0.options.0'))->not->toHaveKey('correct');
    $response->assertJsonMissing(['correct' => true]);
});

it('scores a submission, issues a certificate via the outbox, and verifies it publicly', function () {
    [, , $event, $headers] = makeWebinarHost();
    saveAssessment($event->ulid, $headers);
    setCompletionRule($event->ulid, $headers, minWatch: null, requireAssessment: true);
    $token = registerAttendee($event->ulid, 'Grace Hopper');

    $questionId = $this->withHeaders(['X-Attendee-Token' => $token])
        ->getJson('/api/v1/attend/assessment')->json('data.questions.0.id');

    // Correct answer -> passes.
    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/assessment', ['answers' => [$questionId => 'b']])
        ->assertCreated()
        ->assertJsonPath('data.score', 100)
        ->assertJsonPath('data.passed', true);

    // The outbox issues the certificate (attendee met the rule).
    app(DispatchOutboxAction::class)->execute();
    $this->assertDatabaseHas('certificates', ['recipient_name' => 'Grace Hopper']);

    $code = Certificate::query()->withoutGlobalScopes()->firstOrFail()->code;

    // Public verification (no auth).
    $this->getJson("/api/v1/certificates/verify/{$code}")
        ->assertOk()
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('data.recipient_name', 'Grace Hopper');

    // Host sees the submission and the certificate.
    $this->withHeaders($headers)->getJson("/api/v1/events/{$event->ulid}/education/submissions")
        ->assertOk()->assertJsonPath('data.0.passed', true);
    $this->withHeaders($headers)->getJson("/api/v1/events/{$event->ulid}/education/certificates")
        ->assertOk()->assertJsonPath('data.0.code', $code);
});

it('does not certify a failing submission', function () {
    [, , $event, $headers] = makeWebinarHost();
    saveAssessment($event->ulid, $headers);
    setCompletionRule($event->ulid, $headers, minWatch: null, requireAssessment: true);
    $token = registerAttendee($event->ulid);
    $questionId = $this->withHeaders(['X-Attendee-Token' => $token])->getJson('/api/v1/attend/assessment')->json('data.questions.0.id');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/assessment', ['answers' => [$questionId => 'a']])
        ->assertCreated()
        ->assertJsonPath('data.passed', false);

    app(DispatchOutboxAction::class)->execute();
    $this->assertDatabaseCount('certificates', 0);
});

it('rejects a second submission', function () {
    [, , $event, $headers] = makeWebinarHost();
    saveAssessment($event->ulid, $headers);
    $token = registerAttendee($event->ulid);
    $questionId = $this->withHeaders(['X-Attendee-Token' => $token])->getJson('/api/v1/attend/assessment')->json('data.questions.0.id');

    $this->withHeaders(['X-Attendee-Token' => $token])->postJson('/api/v1/attend/assessment', ['answers' => [$questionId => 'b']])->assertCreated();
    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson('/api/v1/attend/assessment', ['answers' => [$questionId => 'b']])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'already_submitted');
});

it('certifies by attendance alone when watch time is met (host sweep)', function () {
    [, , $event, $headers] = makeWebinarHost();
    setCompletionRule($event->ulid, $headers, minWatch: 60, requireAssessment: false);
    $token = registerAttendee($event->ulid);

    // Join, wait past the threshold, then heartbeat so last_seen advances.
    $this->withHeaders(['X-Attendee-Token' => $token])->postJson('/api/v1/attend/presence/join')->assertOk();
    $this->travel(2)->minutes();
    $this->withHeaders(['X-Attendee-Token' => $token])->postJson('/api/v1/attend/presence/join')->assertOk();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/education/certificates/issue")
        ->assertOk()
        ->assertJsonPath('data.issued', 1);

    $this->assertDatabaseCount('certificates', 1);
});

it('rejects submitting an unpublished assessment', function () {
    [, , $event, $headers] = makeWebinarHost();
    saveAssessment($event->ulid, $headers, published: false);
    $token = registerAttendee($event->ulid);

    $this->withHeaders(['X-Attendee-Token' => $token])->getJson('/api/v1/attend/assessment')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('requires auth for the host and stays public for verification', function () {
    $this->putJson('/api/v1/events/any/education/assessment', ['title' => 'x', 'passing_score' => 50, 'questions' => []])
        ->assertUnauthorized();

    $this->getJson('/api/v1/certificates/verify/UNKNOWN')
        ->assertNotFound()
        ->assertJsonPath('data.valid', false);
});
