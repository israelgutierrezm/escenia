<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * A tenant owner acting via Sanctum plus the host tenant headers.
 *
 * @return array{0: User, 1: \App\Domain\Tenancy\Models\Tenant, 2: array<string, string>}
 */
function enterpriseOwner(?string $email = null, string $tenantName = 'Acme'): array
{
    app(TenantContext::class)->forget();
    [$user, $tenant] = registerTenantOwner($email, $tenantName);
    Sanctum::actingAs($user);

    return [$user, $tenant, ['X-Tenant-Id' => $tenant->ulid]];
}

// ---- Custom domains ----------------------------------------------------------

it('registers and verifies a custom domain', function () {
    [, , $headers] = enterpriseOwner();

    $create = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/domains', ['hostname' => 'events.acme.com'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.hostname', 'events.acme.com')
        ->assertJsonPath('data.dns_challenge.type', 'TXT');

    $id = $create->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/enterprise/domains/{$id}/verify")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('custom_domains', ['hostname' => 'events.acme.com', 'status' => 'active']);
});

it('fails verification when the TXT challenge is absent', function () {
    [, , $headers] = enterpriseOwner();

    $id = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/domains', ['hostname' => 'unverified.acme.com'])
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/enterprise/domains/{$id}/verify")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'domain_verification_failed');

    $this->assertDatabaseHas('custom_domains', ['hostname' => 'unverified.acme.com', 'status' => 'failed']);
});

it('resolves an active custom domain publicly', function () {
    [, $tenant, $headers] = enterpriseOwner();

    $id = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/domains', ['hostname' => 'live.acme.com'])
        ->json('data.id');
    $this->withHeaders($headers)->postJson("/api/v1/enterprise/domains/{$id}/verify")->assertOk();

    app(TenantContext::class)->forget();

    $this->getJson('/api/v1/domains/resolve?hostname=live.acme.com')
        ->assertOk()
        ->assertJsonPath('data.tenant_id', $tenant->ulid)
        ->assertJsonPath('data.hostname', 'live.acme.com');

    $this->getJson('/api/v1/domains/resolve?hostname=nobody.acme.com')->assertNotFound();
});

// ---- API keys ----------------------------------------------------------------

it('issues an API key and authenticates the programmatic API with it', function () {
    [$user, $tenant] = makeEventOwner('Conf');
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $token = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/api-keys', ['name' => 'CI key', 'scopes' => ['events.read']])
        ->assertCreated()
        ->json('data.token');

    expect($token)->toStartWith('esk_');

    app(TenantContext::class)->forget();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/programmatic/events')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects a programmatic request when the key lacks the scope', function () {
    [$user, $tenant] = registerTenantOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $token = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/api-keys', ['name' => 'weak', 'scopes' => ['analytics.read']])
        ->json('data.token');

    app(TenantContext::class)->forget();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/programmatic/events')
        ->assertForbidden();
});

it('revokes an API key so it can no longer authenticate', function () {
    [$user, $tenant] = registerTenantOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $created = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/api-keys', ['name' => 'k', 'scopes' => ['events.read']])
        ->assertCreated();
    $token = $created->json('data.token');
    $id = $created->json('data.key.id');

    $revoked = $this->withHeaders($headers)
        ->deleteJson("/api/v1/enterprise/api-keys/{$id}")
        ->assertOk()
        ->json('data.revoked_at');
    expect($revoked)->not->toBeNull();

    app(TenantContext::class)->forget();

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/programmatic/events')
        ->assertUnauthorized();
});

// ---- SSO ---------------------------------------------------------------------

it('provisions a member via SSO and starts a session', function () {
    [, $tenant, $headers] = enterpriseOwner();

    $conn = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc',
            'display_name' => 'Acme IdP',
            'domain' => 'acme.com',
            'default_role' => 'member',
            'config' => [],
        ])
        ->assertCreated()
        ->json('data.id');

    app(TenantContext::class)->forget();

    $this->postJson("/api/v1/sso/{$conn}/callback", ['code' => 'ok', 'email' => 'dev@acme.com', 'name' => 'Dev'])
        ->assertOk()
        ->assertJsonPath('data.email', 'dev@acme.com');

    $this->assertDatabaseHas('users', ['email' => 'dev@acme.com']);
    $this->assertDatabaseHas('tenant_memberships', ['tenant_id' => $tenant->getKey(), 'role' => 'member']);
});

it('rejects an SSO callback with an invalid code', function () {
    [, , $headers] = enterpriseOwner();

    $conn = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc', 'display_name' => 'Acme', 'default_role' => 'member',
        ])
        ->json('data.id');

    $this->postJson("/api/v1/sso/{$conn}/callback", ['code' => 'invalid'])
        ->assertStatus(401)
        ->assertJsonPath('error_code', 'sso_authentication_failed');
});

it('forbids provisioning an owner via SSO', function () {
    [, , $headers] = enterpriseOwner();

    $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc', 'display_name' => 'X', 'default_role' => 'owner',
        ])
        ->assertStatus(422);
});

it('never exposes the SSO config and stores it encrypted', function () {
    [, , $headers] = enterpriseOwner();

    $res = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc',
            'display_name' => 'Acme',
            'config' => ['client_secret' => 'supersecret'],
        ])
        ->assertCreated()
        ->assertJsonMissingPath('data.config');

    $row = DB::table('sso_connections')->where('ulid', $res->json('data.id'))->first();
    expect($row->config)->not->toContain('supersecret');
});

// ---- Tenant settings ---------------------------------------------------------

it('reads and updates tenant residency settings', function () {
    [, $tenant, $headers] = enterpriseOwner();

    $this->withHeaders($headers)->getJson('/api/v1/enterprise/settings')
        ->assertOk()
        ->assertJsonPath('data.data_region', 'us')
        ->assertJsonPath('data.is_dedicated', false);

    $this->withHeaders($headers)->putJson('/api/v1/enterprise/settings', [
        'data_region' => 'eu', 'is_dedicated' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.data_region', 'eu')
        ->assertJsonPath('data.is_dedicated', true);

    $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey(), 'data_region' => 'eu', 'is_dedicated' => true]);
});

// ---- Advanced audit query ----------------------------------------------------

it('queries the tenant audit log with a filter', function () {
    [, , $headers] = enterpriseOwner();

    $this->withHeaders($headers)->postJson('/api/v1/enterprise/domains', ['hostname' => 'a.acme.com'])->assertCreated();

    $this->withHeaders($headers)->getJson('/api/v1/audit-logs?action=enterprise.domain.created')
        ->assertOk()
        ->assertJsonPath('data.0.action', 'enterprise.domain.created');
});

it('scopes the audit log to the current tenant', function () {
    [$userA, $tenantA] = registerTenantOwner('a@acme.com', 'Acme A');
    Sanctum::actingAs($userA);
    $this->withHeaders(['X-Tenant-Id' => $tenantA->ulid])
        ->postJson('/api/v1/enterprise/domains', ['hostname' => 'x.acme-a.com'])
        ->assertCreated();

    [$userB, $tenantB] = registerTenantOwner('b@beta.com', 'Beta B');
    app(TenantContext::class)->forget();
    Sanctum::actingAs($userB);

    $this->withHeaders(['X-Tenant-Id' => $tenantB->ulid])
        ->getJson('/api/v1/audit-logs?action=enterprise.domain.created')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ---- Authorization -----------------------------------------------------------

it('requires authentication for the enterprise surface', function () {
    $this->getJson('/api/v1/enterprise/domains')->assertUnauthorized();
    $this->getJson('/api/v1/programmatic/events')->assertUnauthorized();
});

it('forbids a non-owner from managing the enterprise surface', function () {
    [$owner, $tenant, $headers] = enterpriseOwner();

    $conn = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc', 'display_name' => 'IdP', 'default_role' => 'member', 'config' => [],
        ])
        ->json('data.id');

    app(TenantContext::class)->forget();
    $this->postJson("/api/v1/sso/{$conn}/callback", ['code' => 'ok', 'email' => 'member@acme.com', 'name' => 'Mem'])
        ->assertOk();

    $member = User::query()->where('email', 'member@acme.com')->firstOrFail();

    app(TenantContext::class)->forget();
    Sanctum::actingAs($member);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->getJson('/api/v1/enterprise/domains')
        ->assertForbidden();
});
