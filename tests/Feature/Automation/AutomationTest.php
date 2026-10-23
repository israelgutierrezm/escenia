<?php

declare(strict_types=1);

use App\Application\Automation\ResumeAutomationRunsAction;
use App\Application\Outbox\DispatchOutboxAction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Webhook hosts resolve to a public address unless a test says otherwise.
beforeEach(fn () => resolveHostsTo('93.184.216.34'));

/**
 * @param  array<string, string>  $headers
 * @param  array<string, mixed>  $payload
 */
function createAutomation(array $headers, array $payload): string
{
    return test()->withHeaders($headers)
        ->postJson('/api/v1/automations', $payload)
        ->assertCreated()
        ->json('data.id');
}

function drainOutbox(): int
{
    return app(DispatchOutboxAction::class)->execute();
}

it('runs a registration automation: tag the contact and notify', function () {
    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'Welcome flow',
        'trigger' => 'registration.completed',
        'steps' => [
            ['type' => 'tag_contact', 'config' => ['tag' => 'registered']],
            ['type' => 'notify', 'config' => ['subject' => 'Welcome!', 'body' => 'Thanks for registering.']],
        ],
    ]);

    registerAttendee($event->ulid, 'Ann', 'ann@example.com');

    expect(drainOutbox())->toBeGreaterThanOrEqual(1);

    $this->assertDatabaseHas('contact_tags', ['tag' => 'registered']);
    $this->assertDatabaseHas('notifications', ['to' => 'ann@example.com', 'subject' => 'Welcome!', 'channel' => 'log']);
    $this->assertDatabaseHas('automation_runs', ['status' => 'completed']);
});

it('only fires when the conditions match', function () {
    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'Never',
        'trigger' => 'registration.completed',
        'conditions' => [['field' => 'event.type', 'op' => 'eq', 'value' => 'no-such-type']],
        'steps' => [['type' => 'tag_contact', 'config' => ['tag' => 'should-not-happen']]],
    ]);

    registerAttendee($event->ulid);
    drainOutbox();

    $this->assertDatabaseMissing('contact_tags', ['tag' => 'should-not-happen']);
    $this->assertDatabaseCount('automation_runs', 0);
});

it('runs a sequence: waits, then resumes when due', function () {
    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'Drip',
        'trigger' => 'registration.completed',
        'steps' => [
            ['type' => 'tag_contact', 'config' => ['tag' => 'step1']],
            ['type' => 'wait', 'config' => ['seconds' => 3600]],
            ['type' => 'tag_contact', 'config' => ['tag' => 'step2']],
        ],
    ]);

    registerAttendee($event->ulid);
    drainOutbox();

    // Parked at the wait: step1 done, step2 pending.
    $this->assertDatabaseHas('contact_tags', ['tag' => 'step1']);
    $this->assertDatabaseMissing('contact_tags', ['tag' => 'step2']);
    $this->assertDatabaseHas('automation_runs', ['status' => 'waiting']);

    // Not due yet.
    expect(app(ResumeAutomationRunsAction::class)->execute())->toBe(0);

    // Travel past the delay -> resumes and completes.
    $this->travel(2)->hours();
    expect(app(ResumeAutomationRunsAction::class)->execute())->toBe(1);

    $this->assertDatabaseHas('contact_tags', ['tag' => 'step2']);
    $this->assertDatabaseHas('automation_runs', ['status' => 'completed']);
});

it('does not double-fire on outbox replay', function () {
    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'Once',
        'trigger' => 'registration.completed',
        'steps' => [['type' => 'tag_contact', 'config' => ['tag' => 'once']]],
    ]);

    registerAttendee($event->ulid);
    drainOutbox();
    drainOutbox(); // processed_at already set; nothing reprocesses

    $this->assertDatabaseCount('automation_runs', 1);
});

it('delivers a signed outbound webhook', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response(['ok' => true], 200)]);

    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'CRM sync',
        'trigger' => 'registration.completed',
        'steps' => [['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/x', 'secret' => 'shh']]],
    ]);

    registerAttendee($event->ulid);
    drainOutbox();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.example.com/x'
        && $request->hasHeader('X-Escenia-Signature', hash_hmac('sha256', $request->body(), 'shh')));
    expect(automationRunSummaries())->toBe(['webhook_sent 200']);
});

/**
 * @return list<string> the step summaries of every run's log
 */
function automationRunSummaries(): array
{
    return DB::table('automation_runs')->pluck('log')
        ->flatMap(fn (string $log): array => array_column(json_decode($log, true), 'summary'))
        ->values()
        ->all();
}

it('never calls a webhook whose host now resolves to an internal address', function () {
    Http::fake();

    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'CRM sync',
        'trigger' => 'registration.completed',
        'steps' => [['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/x']]],
    ]);

    // Public when saved; rebound to the cloud metadata address when it runs.
    resolveHostsTo('169.254.169.254');
    registerAttendee($event->ulid);
    drainOutbox();

    Http::assertNothingSent();
    expect(automationRunSummaries())->toBe(['webhook_blocked (destination not allowed)']);
});

it('refuses webhook destinations the server must not call', function (string $url, string $resolvesTo) {
    resolveHostsTo($resolvesTo);

    [, , , $headers] = makeWebinarHost();

    $this->withHeaders($headers)->postJson('/api/v1/automations', [
        'name' => 'Bad hook',
        'trigger' => 'registration.completed',
        'steps' => [['type' => 'webhook', 'config' => ['url' => $url]]],
    ])->assertUnprocessable()->assertJsonValidationErrors('steps.0.config.url');

    $this->assertDatabaseCount('automations', 0);
})->with([
    'plain http' => ['http://hooks.example.com/x', '93.184.216.34'],
    'cloud metadata address' => ['https://169.254.169.254/latest/meta-data/', '93.184.216.34'],
    'host on the internal network' => ['https://intranet.example.com/hook', '10.0.0.7'],
    'loopback' => ['https://localhost/hook', '127.0.0.1'],
    'not a URL' => ['hooks', '93.184.216.34'],
]);

it('keeps a webhook URL and secret encrypted and out of the API', function () {
    [, , , $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'Chat alert',
        'trigger' => 'registration.completed',
        'steps' => [
            ['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/services/T0/B0/k3yInTheUrl', 'secret' => 'shh-s3cret']],
            ['type' => 'tag_contact', 'config' => ['tag' => 'alerted', 'unused' => 'dropped']],
        ],
    ]);

    $listing = $this->withHeaders($headers)->getJson('/api/v1/automations')
        ->assertOk()
        ->assertJsonPath('data.0.steps.0.config', ['target' => 'https://hooks.example.com'])
        ->assertJsonPath('data.0.steps.0.has_secret', true)
        ->assertJsonPath('data.0.steps.1.config', ['tag' => 'alerted'])
        ->assertJsonMissingPath('data.0.steps.1.has_secret');

    expect($listing->getContent())->not->toContain('k3yInTheUrl')->not->toContain('shh-s3cret');

    $row = DB::table('automation_steps')->where('type', 'webhook')->sole();

    expect($row->config)->toBeNull()
        ->and($row->credentials)->not->toContain('shh-s3cret')
        ->and(json_decode(Crypt::decryptString($row->credentials), true))
        ->toBe(['url' => 'https://hooks.example.com/services/T0/B0/k3yInTheUrl', 'secret' => 'shh-s3cret']);
});

it('validates the settings of each step type and the conditions', function (array $step, string $error) {
    [, , , $headers] = makeWebinarHost();

    $this->withHeaders($headers)->postJson('/api/v1/automations', [
        'name' => 'Invalid',
        'trigger' => 'registration.completed',
        'steps' => [$step],
    ])->assertUnprocessable()->assertJsonValidationErrors($error);
})->with([
    'webhook without a URL' => [['type' => 'webhook', 'config' => ['secret' => 'x']], 'steps.0.config.url'],
    'tag without a tag' => [['type' => 'tag_contact', 'config' => []], 'steps.0.config.tag'],
    'notification without a subject' => [['type' => 'notify', 'config' => ['body' => 'Hi']], 'steps.0.config.subject'],
    'wait of zero seconds' => [['type' => 'wait', 'config' => ['seconds' => 0]], 'steps.0.config.seconds'],
    'unknown condition operator' => [[
        'type' => 'tag_contact',
        'config' => ['tag' => 'x'],
        'conditions' => [['field' => 'event.type', 'op' => 'matches', 'value' => 'x']],
    ], 'steps.0.conditions.0.op'],
]);

it('moves webhook credentials out of the plain config, reversibly', function () {
    Http::fake();

    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'Legacy hook',
        'trigger' => 'registration.completed',
        'steps' => [['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/x', 'secret' => 'shh']]],
    ]);

    $migration = require database_path('migrations/2026_10_22_000000_move_automation_webhook_credentials.php');

    // Rolled back: the step looks like one saved before ADR-037.
    $migration->down();
    expect(json_decode((string) DB::table('automation_steps')->sole()->config, true))
        ->toBe(['url' => 'https://hooks.example.com/x', 'secret' => 'shh']);

    $migration->up();
    $row = DB::table('automation_steps')->sole();

    expect($row->config)->toBeNull()
        ->and(json_decode(Crypt::decryptString($row->credentials), true))
        ->toBe(['url' => 'https://hooks.example.com/x', 'secret' => 'shh']);

    registerAttendee($event->ulid);
    drainOutbox();

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Escenia-Signature', hash_hmac('sha256', $request->body(), 'shh')));
});

it('triggers an automation on a paid order', function () {
    [, , $event, $headers, $account] = makeCommerceHost();

    createAutomation($headers, [
        'name' => 'Thank buyers',
        'trigger' => 'order.paid',
        'conditions' => [['field' => 'order.total_minor', 'op' => 'gte', 'value' => 1000]],
        'steps' => [['type' => 'tag_contact', 'config' => ['tag' => 'customer']]],
    ]);

    $ticket = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/tickets", ['name' => 'GA', 'amount_minor' => 5000, 'currency' => 'USD'])
        ->json('data.id');
    $reference = $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob', 'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]],
    ])->json('data.payment.reference');
    $this->withHeaders(['X-Fake-Signature' => 'whsec_test'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", ['reference' => $reference, 'outcome' => 'succeeded'])
        ->assertOk();

    drainOutbox();

    $this->assertDatabaseHas('contact_tags', ['tag' => 'customer']);
});

it('requires auth to manage automations', function () {
    $this->postJson('/api/v1/automations', ['name' => 'X', 'trigger' => 'order.paid', 'steps' => []])
        ->assertUnauthorized();
});

it('lets the host toggle and inspect runs', function () {
    [, , $event, $headers] = makeWebinarHost();

    $automationId = createAutomation($headers, [
        'name' => 'Toggle me',
        'trigger' => 'registration.completed',
        'steps' => [['type' => 'tag_contact', 'config' => ['tag' => 't']]],
    ]);

    // Deactivate -> no run on the next registration.
    $this->withHeaders($headers)->postJson("/api/v1/automations/{$automationId}/active", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    registerAttendee($event->ulid);
    drainOutbox();

    $this->withHeaders($headers)->getJson("/api/v1/automations/{$automationId}/runs")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
