<?php

declare(strict_types=1);

use App\Application\Events\Actions\CreateEventAction;
use App\Application\Events\DTOs\CreateEventData;
use App\Application\Identity\Actions\RegisterUserAction;
use App\Application\Identity\DTOs\RegisterUserData;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Register a brand new user together with their first tenant (owner), returning
 * both. Mirrors the real registration path used across the API.
 *
 * @return array{0: User, 1: Tenant}
 */
function registerTenantOwner(?string $email = null, string $tenantName = 'Acme'): array
{
    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Test User',
        email: $email ?? fake()->unique()->safeEmail(),
        password: 'password',
        tenantName: $tenantName,
    ));

    /** @var Tenant $tenant */
    $tenant = $user->tenants()->firstOrFail();

    return [$user, $tenant];
}

/**
 * A tenant owner plus a freshly created event in their default workspace.
 *
 * @return array{0: User, 1: Tenant, 2: Event}
 */
function makeEventOwner(string $title = 'Studio Event'): array
{
    // Start each fixture from a clean tenant context. In production every HTTP
    // request resets the scoped TenantContext; the test process reuses one
    // container across sub-requests, so we reset it explicitly here.
    app(TenantContext::class)->forget();

    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();

    $event = app(CreateEventAction::class)->execute($tenant, $workspace, $user, new CreateEventData($title));

    return [$user, $tenant, $event];
}

/**
 * A tenant owner acting via Sanctum, with an event whose registration form is
 * open. Returns the owner, tenant, event and the host auth headers.
 *
 * @return array{0: User, 1: Tenant, 2: Event, 3: array<string, string>}
 */
function makeWebinarHost(string $title = 'Webinar'): array
{
    [$user, $tenant, $event] = makeEventOwner($title);
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    test()->withHeaders($headers)
        ->putJson("/api/v1/events/{$event->ulid}/registration-form", ['is_open' => true, 'fields' => []])
        ->assertOk();

    return [$user, $tenant, $event, $headers];
}

/**
 * A tenant owner acting via Sanctum, with an event and a connected `fake`
 * payment gateway (webhook secret `whsec_test`). Returns owner, tenant, event,
 * host headers and the connected account payload.
 *
 * @return array{0: User, 1: Tenant, 2: Event, 3: array<string, string>, 4: array<string, mixed>}
 */
function makeCommerceHost(string $title = 'Paid Event'): array
{
    [$user, $tenant, $event] = makeEventOwner($title);
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $account = test()->withHeaders($headers)
        ->putJson('/api/v1/payment-accounts', [
            'gateway' => 'fake',
            'display_name' => 'Test Gateway',
            'currency' => 'USD',
            'credentials' => [],
            'webhook_secret' => 'whsec_test',
        ])
        ->assertOk()
        ->json('data');

    return [$user, $tenant, $event, $headers, $account];
}

/**
 * Register a public attendee for an event and return their raw join token
 * (the credential for attendee-facing endpoints).
 */
function registerAttendee(string $eventUlid, string $name = 'Ava Attendee', ?string $email = null): string
{
    return test()->postJson("/api/v1/events/{$eventUlid}/register", [
        'name' => $name,
        'email' => $email ?? fake()->unique()->safeEmail(),
    ])->assertCreated()->json('data.token');
}

/**
 * A tenant owner acting via Sanctum with the host tenant header.
 *
 * @return array{0: User, 1: Tenant, 2: array<string, string>}
 */
function ssoTenantOwner(?string $email = null, string $tenantName = 'Acme'): array
{
    app(TenantContext::class)->forget();
    [$user, $tenant] = registerTenantOwner($email, $tenantName);
    Sanctum::actingAs($user);

    return [$user, $tenant, ['X-Tenant-Id' => $tenant->ulid]];
}

/**
 * A minimal OpenSSL config, so test keys and certificates can be generated at
 * runtime even where PHP ships without one (Windows builds). No key material
 * lives in the repo.
 */
function opensslTestConfig(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escenia-test-openssl.cnf';
    // PHP checks the key length even for EC keys, reading it from the config.
    $contents = "[ req ]\ndefault_bits = 2048\ndistinguished_name = dn\n[ dn ]\n";

    if (! is_file($path) || file_get_contents($path) !== $contents) {
        file_put_contents($path, $contents);
    }

    return $path;
}

/**
 * Create an SSO connection as the acting tenant owner and verify its email
 * domain (the fake DNS verifier passes). Returns the connection's public id.
 *
 * @param  array<string, string>  $headers
 * @param  array<string, mixed>  $attributes
 */
function makeVerifiedSsoConnection(array $headers, array $attributes = []): string
{
    $id = test()->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', array_merge([
            'provider' => 'oidc',
            'display_name' => 'Acme IdP',
            'domain' => 'acme.com',
            'default_role' => 'member',
        ], $attributes))
        ->assertCreated()
        ->json('data.id');

    test()->withHeaders($headers)
        ->postJson("/api/v1/enterprise/sso-connections/{$id}/verify-domain")
        ->assertOk()
        ->assertJsonPath('data.domain_verified', true);

    return $id;
}

/**
 * The public SSO round trip as a browser runs it: start (state in the body,
 * binding in a cookie), then post the callback carrying both. JSON test
 * requests only send cookies `withCredentials()`, like `fetch` in the SPA.
 *
 * @param  array<string, mixed>  $body  extra callback fields (fake-provider hints)
 */
function completeSsoLogin(string $connection, array $body = []): TestResponse
{
    app(TenantContext::class)->forget();

    $start = test()->getJson("/api/v1/sso/{$connection}")->assertOk();

    return test()
        ->withCredentials()
        ->withUnencryptedCookie('escenia_sso', (string) $start->getCookie('escenia_sso', false)?->getValue())
        ->postJson("/api/v1/sso/{$connection}/callback", array_merge([
            'code' => 'ok',
            'state' => $start->json('data.state'),
        ], $body));
}
