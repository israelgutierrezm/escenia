<?php

declare(strict_types=1);

use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Enterprise\Domains\DisabledDomainVerifier;
use App\Infrastructure\Enterprise\Sso\DisabledIdentityProvider;
use Laravel\Sanctum\Sanctum;

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

it('provisions a member from the verified domain and starts a session', function () {
    [, $tenant, $headers] = ssoTenantOwner();
    $conn = makeVerifiedSsoConnection($headers);

    completeSsoLogin($conn, ['email' => 'dev@acme.com', 'name' => 'Dev'])
        ->assertOk()
        ->assertJsonPath('data.email', 'dev@acme.com');

    $dev = User::query()->where('email', 'dev@acme.com')->firstOrFail();
    $this->assertDatabaseHas('tenant_memberships', [
        'tenant_id' => $tenant->getKey(), 'user_id' => $dev->getKey(), 'role' => 'member',
    ]);
    $this->assertAuthenticatedAs($dev, 'web');
});

it('never lets a connection vouch for an email outside its verified domain', function () {
    // The victim has an account (and their own tenant) elsewhere.
    [$victim] = registerTenantOwner('victim@victim.org', 'Victim Co');

    // Any registered user owns a tenant and may configure SSO for it.
    [, $evil, $headers] = ssoTenantOwner('attacker@evil.test', 'Evil');
    $conn = makeVerifiedSsoConnection($headers, ['domain' => 'evil.test']);

    completeSsoLogin($conn, ['email' => 'victim@victim.org'])
        ->assertStatus(401)
        ->assertJsonPath('error_code', 'sso_authentication_failed');

    $this->assertGuest('web');
    $this->assertDatabaseMissing('tenant_memberships', [
        'tenant_id' => $evil->getKey(), 'user_id' => $victim->getKey(),
    ]);
    // Refused by the domain rule itself (the state and binding were valid).
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'enterprise.sso.login_rejected', 'context->reason' => 'email_outside_domain',
    ]);
});

it('refuses SSO logins until the connection domain is verified', function () {
    [, , $headers] = ssoTenantOwner();

    $conn = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc', 'display_name' => 'Acme', 'domain' => 'acme.com', 'default_role' => 'member',
        ])
        ->assertCreated()
        ->assertJsonPath('data.domain_verified', false)
        ->json('data.id');

    completeSsoLogin($conn, ['email' => 'dev@acme.com'])->assertStatus(401);

    $this->assertDatabaseMissing('users', ['email' => 'dev@acme.com']);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'enterprise.sso.login_rejected', 'context->reason' => 'domain_not_verified',
    ]);
});

it('verifies the SSO email domain through its DNS challenge', function () {
    [, , $headers] = ssoTenantOwner();

    $created = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc', 'display_name' => 'Acme', 'domain' => 'Acme.com', 'default_role' => 'member',
        ])
        ->assertCreated()
        ->assertJsonPath('data.domain', 'acme.com')
        ->assertJsonPath('data.dns_challenge.type', 'TXT')
        ->assertJsonPath('data.dns_challenge.name', '_escenia-sso.acme.com');

    expect($created->json('data.dns_challenge.value'))->toBeString()->not->toBeEmpty();

    $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections/'.$created->json('data.id').'/verify-domain')
        ->assertOk()
        ->assertJsonPath('data.domain_verified', true);
});

it('keeps the domain unverified when the DNS challenge fails', function () {
    [, , $headers] = ssoTenantOwner();

    $conn = $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc', 'display_name' => 'Acme', 'domain' => 'unverified.acme.com', 'default_role' => 'member',
        ])
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/enterprise/sso-connections/{$conn}/verify-domain")
        ->assertStatus(422);

    $this->withHeaders($headers)->getJson('/api/v1/enterprise/sso-connections')
        ->assertJsonPath('data.0.domain_verified', false);
});

it('resets the domain verification when the domain changes', function () {
    [, , $headers] = ssoTenantOwner();
    $conn = makeVerifiedSsoConnection($headers);

    $this->withHeaders($headers)
        ->patchJson("/api/v1/enterprise/sso-connections/{$conn}", ['domain' => 'acme.io'])
        ->assertOk()
        ->assertJsonPath('data.domain', 'acme.io')
        ->assertJsonPath('data.domain_verified', false);

    completeSsoLogin($conn, ['email' => 'dev@acme.io'])->assertStatus(401);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'enterprise.sso.login_rejected', 'context->reason' => 'domain_not_verified',
    ]);
});

it('rejects a domain that is not a bare hostname', function (string $domain) {
    [, , $headers] = ssoTenantOwner();

    $this->withHeaders($headers)
        ->postJson('/api/v1/enterprise/sso-connections', [
            'provider' => 'oidc', 'display_name' => 'Acme', 'domain' => $domain, 'default_role' => 'member',
        ])
        ->assertStatus(422);
})->with(['', 'https://acme.com', 'dev@acme.com', 'acme', '-acme.com']);

it('requires the state issued by the start step', function () {
    [, , $headers] = ssoTenantOwner();
    $conn = makeVerifiedSsoConnection($headers);
    app(TenantContext::class)->forget();

    $this->postJson("/api/v1/sso/{$conn}/callback", ['code' => 'ok', 'email' => 'dev@acme.com'])
        ->assertStatus(422);

    $this->postJson("/api/v1/sso/{$conn}/callback", ['code' => 'ok', 'state' => 'forged', 'email' => 'dev@acme.com'])
        ->assertStatus(401);
});

it('consumes the state on first use so it cannot be replayed', function () {
    [, , $headers] = ssoTenantOwner();
    $conn = makeVerifiedSsoConnection($headers);
    app(TenantContext::class)->forget();

    $start = $this->getJson("/api/v1/sso/{$conn}")->assertOk();
    $this->withCredentials()->withUnencryptedCookie('escenia_sso', (string) $start->getCookie('escenia_sso', false)?->getValue());
    $body = ['code' => 'ok', 'state' => $start->json('data.state'), 'email' => 'dev@acme.com'];

    $this->postJson("/api/v1/sso/{$conn}/callback", $body)->assertOk();
    $this->postJson("/api/v1/sso/{$conn}/callback", $body)->assertStatus(401);
});

it('binds the state to the browser that started the login', function () {
    [, , $headers] = ssoTenantOwner();
    $conn = makeVerifiedSsoConnection($headers);
    app(TenantContext::class)->forget();

    $start = $this->getJson("/api/v1/sso/{$conn}")->assertOk();
    $body = ['code' => 'ok', 'state' => $start->json('data.state'), 'email' => 'dev@acme.com'];

    // A state planted in another browser (login CSRF / code injection) is useless
    // without the binding cookie — and a failed attempt does not burn the state.
    $this->postJson("/api/v1/sso/{$conn}/callback", $body)->assertStatus(401);
    $this->withCredentials()->withUnencryptedCookie('escenia_sso', 'not-the-binding')
        ->postJson("/api/v1/sso/{$conn}/callback", $body)->assertStatus(401);

    $this->withUnencryptedCookie('escenia_sso', (string) $start->getCookie('escenia_sso', false)?->getValue())
        ->postJson("/api/v1/sso/{$conn}/callback", $body)->assertOk();
});

it('rejects a state that was issued for another connection', function () {
    [, , $headers] = ssoTenantOwner();
    $first = makeVerifiedSsoConnection($headers);
    $second = makeVerifiedSsoConnection($headers, ['display_name' => 'Acme backup']);
    app(TenantContext::class)->forget();

    $start = $this->getJson("/api/v1/sso/{$first}")->assertOk();

    // Valid state and binding — only the connection differs.
    $this->withCredentials()
        ->withUnencryptedCookie('escenia_sso', (string) $start->getCookie('escenia_sso', false)?->getValue())
        ->postJson("/api/v1/sso/{$second}/callback", [
            'code' => 'ok', 'state' => $start->json('data.state'), 'email' => 'dev@acme.com',
        ])
        ->assertStatus(401);
});

it('fails closed when the fake providers are configured in production', function () {
    [, , $headers] = ssoTenantOwner();
    $conn = makeVerifiedSsoConnection($headers);

    app()['env'] = 'production';
    app()->forgetInstance(IdentityProvider::class);
    app()->forgetInstance(DomainVerifier::class);

    expect(app(IdentityProvider::class))->toBeInstanceOf(DisabledIdentityProvider::class)
        ->and(app(DomainVerifier::class))->toBeInstanceOf(DisabledDomainVerifier::class);

    app(TenantContext::class)->forget();
    $this->getJson("/api/v1/sso/{$conn}")->assertStatus(401);
});
