<?php

declare(strict_types=1);

use App\Application\Automation\ResumeAutomationRunsAction;
use App\Application\Outbox\DispatchOutboxAction;
use Illuminate\Support\Facades\Http;

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
        && $request->hasHeader('X-Escenia-Signature'));
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
