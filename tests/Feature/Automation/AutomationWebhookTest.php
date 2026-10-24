<?php

declare(strict_types=1);

use App\Application\Automation\AutomationExecutor;
use App\Application\Automation\ResumeAutomationRunsAction;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Contracts\Notifier;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Registration\Models\Contact;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use App\Infrastructure\Http\HostResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

// Webhook hosts resolve to a public address; retries without jitter (ADR-038).
beforeEach(function () {
    resolveHostsTo('93.184.216.34');
    config(['automation.retries.jitter' => 0]);
});

/**
 * Creates `[...$before, webhook, tag_contact]` and returns the automation id,
 * the webhook step id and the secret issued for it.
 *
 * @param  array<string, string>  $headers
 * @param  array<string, mixed>  $config
 * @param  list<array<string, mixed>>  $before
 * @return array{0: string, 1: string, 2: string}
 */
function createWebhookAutomation(array $headers, array $config = [], array $before = []): array
{
    $response = test()->withHeaders($headers)->postJson('/api/v1/automations', [
        'name' => 'CRM sync',
        'trigger' => 'registration.completed',
        'steps' => [
            ...$before,
            ['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/x'] + $config],
            ['type' => 'tag_contact', 'config' => ['tag' => 'after-webhook']],
        ],
    ])->assertCreated();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    return [$response->json('data.id'), $response->json('webhook_secrets.0.step.id'), $response->json('webhook_secrets.0.secret')];
}

function webhookRun(): AutomationRun
{
    return AutomationRun::query()->withoutGlobalScopes()->sole();
}

function resumeDueRuns(): int
{
    return app(ResumeAutomationRunsAction::class)->execute();
}

/**
 * Turns the webhook step into one saved before ADR-038, with a free-form secret.
 */
function makeWebhookLegacy(string $secret): void
{
    DB::table('automation_steps')->where('type', 'webhook')->update([
        'credentials' => Crypt::encryptString((string) json_encode(['url' => 'https://hooks.example.com/x', 'secret' => $secret])),
    ]);
}

function automationViewer(Tenant $tenant): User
{
    $user = User::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
        'role' => TenantRole::Member,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->assignRole(TenantRole::Member->value);

    return $user;
}

// ---- Signing (Standard Webhooks) --------------------------------------------

it('signs deliveries with the Standard Webhooks scheme and a server-issued secret', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('', 204)]);

    [, , $event, $headers] = makeWebinarHost();
    [, , $secret] = createWebhookAutomation($headers);

    expect($secret)->toMatch('/^whsec_[A-Za-z0-9+\/]{43}=$/');

    registerAttendee($event->ulid);
    drainOutbox();

    $run = webhookRun();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('webhook-id', "msg_{$run->ulid}_0")
        && abs((int) ($request->header('webhook-timestamp')[0] ?? 0) - now()->getTimestamp()) <= 5
        && $request->hasHeader('webhook-signature', standardWebhookSignature($secret, $request))
        && ! $request->hasHeader('X-Escenia-Signature'));

    // The secret never comes back from the API.
    expect($this->withHeaders($headers)->getJson('/api/v1/automations')->getContent())->not->toContain($secret);
});

it('accepts a Standard Webhooks secret brought by the tenant and refuses any other', function () {
    Http::fake();

    [, , $event, $headers] = makeWebinarHost();
    $own = 'whsec_'.base64_encode(random_bytes(32));

    [, , $issued] = createWebhookAutomation($headers, ['secret' => $own]);
    expect($issued)->toBe($own);

    registerAttendee($event->ulid);
    drainOutbox();
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('webhook-signature', standardWebhookSignature($own, $request)));

    $this->withHeaders($headers)->postJson('/api/v1/automations', [
        'name' => 'Weak',
        'trigger' => 'registration.completed',
        'steps' => [['type' => 'webhook', 'config' => ['url' => 'https://hooks.example.com/x', 'secret' => 'shh']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('steps.0.config.secret');
});

it('keeps signing a pre-ADR-038 secret with the old header only', function () {
    Http::fake();

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers);
    makeWebhookLegacy('shh');

    registerAttendee($event->ulid);
    drainOutbox();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Escenia-Signature', hash_hmac('sha256', $request->body(), 'shh'))
        && $request->hasHeader('webhook-id')
        && $request->hasHeader('webhook-timestamp')
        && ! $request->hasHeader('webhook-signature'));

    $this->withHeaders($headers)->getJson('/api/v1/automations')
        ->assertJsonPath('data.0.steps.0.legacy_signature', true)
        ->assertJsonPath('data.0.steps.0.has_secret', true);
});

// ---- Rotation ---------------------------------------------------------------

it('rotates a secret and keeps the previous one signing during the grace period', function () {
    Http::fake();

    [, , $event, $headers] = makeWebinarHost();
    [$automation, $step, $old] = createWebhookAutomation($headers);

    $rotated = $this->withHeaders($headers)->postJson("/api/v1/automations/{$automation}/steps/{$step}/secret")->assertOk();
    $new = $rotated->json('data.secret');

    expect($rotated->headers->get('Cache-Control'))->toContain('no-store')
        ->and($new)->toMatch('/^whsec_/')->not->toBe($old)
        ->and($rotated->json('data.step.previous_secret_expires_at'))->not->toBeNull();

    registerAttendee($event->ulid, 'Ann', 'ann@example.com');
    drainOutbox();
    Http::assertSent(fn (Request $request): bool => $request->hasHeader(
        'webhook-signature',
        standardWebhookSignature($new, $request).' '.standardWebhookSignature($old, $request),
    ));

    $this->travel(25)->hours();
    registerAttendee($event->ulid, 'Bob', 'bob@example.com');
    drainOutbox();

    [$last] = Http::recorded()->last();
    expect($last->header('webhook-signature'))->toBe([standardWebhookSignature($new, $last)]);
});

it('moves a pre-ADR-038 step to the standard signature when its secret is rotated', function () {
    Http::fake();

    [, , $event, $headers] = makeWebinarHost();
    [$automation, $step] = createWebhookAutomation($headers);
    makeWebhookLegacy('shh');

    $new = $this->withHeaders($headers)->postJson("/api/v1/automations/{$automation}/steps/{$step}/secret")->assertOk()->json('data.secret');

    registerAttendee($event->ulid, 'Ann', 'ann@example.com');
    drainOutbox();
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('webhook-signature', standardWebhookSignature($new, $request))
        && $request->hasHeader('X-Escenia-Signature', hash_hmac('sha256', $request->body(), 'shh')));

    $this->travel(25)->hours();
    registerAttendee($event->ulid, 'Bob', 'bob@example.com');
    drainOutbox();

    [$last] = Http::recorded()->last();
    expect($last->hasHeader('X-Escenia-Signature'))->toBeFalse();

    $this->withHeaders($headers)->getJson('/api/v1/automations')->assertJsonPath('data.0.steps.0.legacy_signature', false);
});

it('lets only managers rotate secrets of their own webhook steps', function () {
    [, $tenant, , $headers] = makeWebinarHost();
    [$automation, $step] = createWebhookAutomation($headers);
    $tag = $this->withHeaders($headers)->getJson('/api/v1/automations')->json('data.0.steps.1.id');

    $this->withHeaders($headers)->postJson("/api/v1/automations/{$automation}/steps/{$tag}/secret")
        ->assertUnprocessable()->assertJsonValidationErrors('step');

    Sanctum::actingAs(automationViewer($tenant));
    $this->withHeaders($headers)->postJson("/api/v1/automations/{$automation}/steps/{$step}/secret")->assertForbidden();

    [, , , $otherHeaders] = makeWebinarHost('Other');
    $this->withHeaders($otherHeaders)->postJson("/api/v1/automations/{$automation}/steps/{$step}/secret")->assertNotFound();

    expect(DB::table('audit_logs')->where('action', 'automation.webhook_secret_rotated')->count())->toBe(0);
});

it('audits a rotation without the secret', function () {
    [, , , $headers] = makeWebinarHost();
    [$automation, $step] = createWebhookAutomation($headers);

    $new = $this->withHeaders($headers)->postJson("/api/v1/automations/{$automation}/steps/{$step}/secret")->assertOk()->json('data.secret');

    $audit = DB::table('audit_logs')->where('action', 'automation.webhook_secret_rotated')->sole();
    expect(json_encode($audit))->not->toContain($new)->not->toContain(substr($new, 6, 20));
});

// ---- Retries ----------------------------------------------------------------

it('retries a failed delivery with the same webhook-id until it succeeds', function () {
    Http::fake(['https://hooks.example.com/*' => Http::sequence()->push('', 503)->pushFailedConnection()->push('', 200)]);

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers, before: [['type' => 'notify', 'config' => ['subject' => 'Hola', 'body' => 'Bienvenida']]]);

    registerAttendee($event->ulid);
    drainOutbox();

    $run = webhookRun();
    expect($run->status->value)->toBe('waiting')
        ->and($run->current_position)->toBe(1)
        ->and($run->step_attempts)->toBe(1)
        ->and($run->resume_at?->getTimestamp() - now()->getTimestamp())->toBeBetween(59, 61);

    $this->travel(61)->seconds();
    expect(resumeDueRuns())->toBe(1)->and(webhookRun()->step_attempts)->toBe(2);

    $this->travel(301)->seconds();
    resumeDueRuns();

    $run = webhookRun();
    $requests = collect(Http::recorded())->map(fn (array $pair): Request => $pair[0]);

    expect($run->status->value)->toBe('completed')
        ->and($requests)->toHaveCount(3)
        ->and($requests->map(fn (Request $r): string => $r->header('webhook-id')[0])->unique()->all())->toBe(["msg_{$run->ulid}_1"])
        ->and($requests->map(fn (Request $r): string => $r->header('webhook-timestamp')[0])->unique())->toHaveCount(3)
        ->and(array_column($run->log ?? [], 'outcome'))->toBe(['done', 'retry', 'retry', 'done', 'done'])
        // The step before the webhook ran exactly once.
        ->and(DB::table('notifications')->count())->toBe(1);
});

it('gives up after the last attempt and lets the sequence go on', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('', 500)]);

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers);

    registerAttendee($event->ulid);
    drainOutbox();

    foreach ([60, 300, 1800, 7200, 18000] as $delay) {
        $this->travel($delay + 1)->seconds();
        resumeDueRuns();
    }

    $run = webhookRun();
    Http::assertSentCount(6);

    expect($run->status->value)->toBe('completed')
        ->and(automationRunSummaries())->toContain('webhook_failed 500 (gave up)')
        ->and(collect($run->log)->last())->toMatchArray(['summary' => 'tagged: after-webhook', 'outcome' => 'done']);
});

it('does not retry a permanent failure', function (int $status) {
    Http::fake(['https://hooks.example.com/*' => Http::response('', $status)]);

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers);

    registerAttendee($event->ulid);
    drainOutbox();

    Http::assertSentCount(1);
    expect(webhookRun()->status->value)->toBe('completed')
        ->and(collect(webhookRun()->log)->firstWhere('type', 'webhook'))->toMatchArray(['summary' => "webhook_failed {$status}", 'outcome' => 'failed']);
})->with([404, 410, 302]);

it('honours Retry-After', function (string $header, int $seconds) {
    Http::fake(['https://hooks.example.com/*' => Http::response('', 429, ['Retry-After' => $header])]);

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers);

    registerAttendee($event->ulid);
    drainOutbox();

    expect(webhookRun()->resume_at?->getTimestamp() - now()->getTimestamp())->toBeBetween($seconds - 2, $seconds + 2);
})->with([
    'seconds' => ['900', 900],
    'http date' => fn (): array => [now()->addSeconds(1200)->utc()->format(DATE_RFC7231), 1200],
]);

it('retries when the host does not resolve at send time', function () {
    Http::fake();

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers);

    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return [];
        }
    });

    registerAttendee($event->ulid);
    drainOutbox();

    Http::assertNothingSent();
    expect(webhookRun()->status->value)->toBe('waiting')
        ->and(collect(webhookRun()->log)->last())->toMatchArray(['summary' => 'webhook_failed (host does not resolve)', 'outcome' => 'retry']);
});

it('retries a step that throws, then fails the run without repeating earlier steps', function () {
    [, , $event, $headers] = makeWebinarHost();

    createAutomation($headers, [
        'name' => 'Flaky',
        'trigger' => 'registration.completed',
        'steps' => [
            ['type' => 'tag_contact', 'config' => ['tag' => 'first']],
            ['type' => 'notify', 'config' => ['subject' => 'Hola', 'body' => 'Bienvenida']],
        ],
    ]);

    app()->instance(Notifier::class, new class implements Notifier
    {
        public function send(string $to, string $subject, string $body, ?Contact $contact = null): Notification
        {
            throw new RuntimeException(str_repeat('secret detail ', 30));
        }
    });

    registerAttendee($event->ulid);
    drainOutbox();

    expect(webhookRun()->status->value)->toBe('waiting')
        ->and(webhookRun()->current_position)->toBe(1)
        ->and(collect(webhookRun()->log)->last())->toMatchArray(['summary' => 'error: RuntimeException', 'outcome' => 'retry']);

    foreach ([60, 300, 1800] as $delay) {
        $this->travel($delay + 1)->seconds();
        resumeDueRuns();
    }

    $run = webhookRun();

    expect($run->status->value)->toBe('failed')
        ->and(strlen((string) $run->failed_reason))->toBeLessThanOrEqual(255)
        ->and(collect($run->log)->where('position', 0))->toHaveCount(1)
        ->and(json_encode($run->log))->not->toContain('secret detail');
});

// ---- Claim and lease --------------------------------------------------------

it('lets only one worker claim a due run', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('', 503)]);

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers);

    registerAttendee($event->ulid);
    drainOutbox();
    $this->travel(61)->seconds();

    $first = webhookRun();
    $second = webhookRun();
    $resume = app(ResumeAutomationRunsAction::class);

    expect($resume->claim($first))->toBeTrue()
        ->and($resume->claim($second))->toBeFalse();
});

it('stops a worker that lost its lease instead of overwriting the run', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('', 503)]);

    [, , $event, $headers] = makeWebinarHost();
    createWebhookAutomation($headers);

    registerAttendee($event->ulid);
    drainOutbox();
    $this->travel(61)->seconds();

    // Worker A takes the run and stalls past its lease…
    $stale = webhookRun();
    expect(app(ResumeAutomationRunsAction::class)->claim($stale))->toBeTrue();
    $this->travel(AutomationRun::LEASE_SECONDS + 1)->seconds();

    // …worker B picks it up and makes the next attempt…
    expect(resumeDueRuns())->toBe(1);
    $afterB = webhookRun();

    // …and when A wakes up, its writes are refused.
    app(AutomationExecutor::class)->run($stale);

    expect(webhookRun()->log)->toBe($afterB->log)
        ->and(webhookRun()->step_attempts)->toBe($afterB->step_attempts)
        ->and(webhookRun()->resume_at?->getTimestamp())->toBe($afterB->resume_at?->getTimestamp());
});
