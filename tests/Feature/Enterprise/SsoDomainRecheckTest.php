<?php

declare(strict_types=1);

use App\Application\Enterprise\Jobs\ReverifySsoDomainJob;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\DTOs\DnsChallenge;
use App\Domain\Enterprise\DTOs\DomainVerificationResult;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Support\Facades\Queue;

function recheckConnection(string $ulid): SsoConnection
{
    return SsoConnection::query()->withoutGlobalScopes()->where('ulid', $ulid)->firstOrFail();
}

function runDomainRecheck(SsoConnection $connection): void
{
    (new ReverifySsoDomainJob($connection->id))->handle(app(DomainVerifier::class), app(AuditLogger::class));
}

/**
 * The tenant removed (or lost) the TXT record.
 */
function failDomainChallenges(): void
{
    app()->instance(DomainVerifier::class, new class implements DomainVerifier
    {
        public function verify(DnsChallenge $challenge): DomainVerificationResult
        {
            return DomainVerificationResult::failure('TXT record not found.');
        }
    });
}

it('keeps a domain verified while its DNS challenge still passes', function () {
    [, , $headers] = ssoTenantOwner();
    $connection = recheckConnection(makeVerifiedSsoConnection($headers));
    $connection->forceFill(['domain_checked_at' => now()->subDays(2)])->save();

    runDomainRecheck($connection);

    $connection->refresh();
    expect($connection->hasVerifiedDomain())->toBeTrue()
        ->and($connection->domain_checked_at?->isToday())->toBeTrue()
        ->and($connection->domain_check_failed_at)->toBeNull();
});

it('gives a failing domain a grace period, then revokes its verification', function () {
    [, , $headers] = ssoTenantOwner();
    $ulid = makeVerifiedSsoConnection($headers);
    $connection = recheckConnection($ulid);
    failDomainChallenges();

    runDomainRecheck($connection);

    $connection->refresh();
    expect($connection->hasVerifiedDomain())->toBeTrue()
        ->and($connection->domain_check_failed_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'enterprise.sso.domain_check_failed']);

    $this->travel(73)->hours();
    runDomainRecheck($connection->refresh());

    $connection->refresh();
    expect($connection->hasVerifiedDomain())->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['action' => 'enterprise.sso.domain_unverified']);

    // The connection no longer vouches for anyone.
    completeSsoLogin($ulid, ['email' => 'dev@acme.com'])->assertStatus(401);
});

it('clears the failure once the challenge passes again within the grace period', function () {
    [, , $headers] = ssoTenantOwner();
    $connection = recheckConnection(makeVerifiedSsoConnection($headers));

    failDomainChallenges();
    runDomainRecheck($connection);
    app()->forgetInstance(DomainVerifier::class); // back to the passing fake

    $this->travel(24)->hours();
    runDomainRecheck($connection->refresh());

    $connection->refresh();
    expect($connection->hasVerifiedDomain())->toBeTrue()
        ->and($connection->domain_check_failed_at)->toBeNull();
});

it('queues re-checks only for verified domains not checked in the last day', function () {
    [, , $headers] = ssoTenantOwner();
    $fresh = makeVerifiedSsoConnection($headers);
    $stale = recheckConnection(makeVerifiedSsoConnection($headers, ['display_name' => 'Stale']));
    $stale->forceFill(['domain_checked_at' => now()->subDays(2)])->save();
    $this->withHeaders($headers)->postJson('/api/v1/enterprise/sso-connections', [
        'provider' => 'oidc', 'display_name' => 'Unverified', 'domain' => 'acme.com', 'default_role' => 'member',
    ])->assertCreated();

    Queue::fake();
    $this->artisan('enterprise:reverify-sso-domains')->assertSuccessful();

    Queue::assertPushed(ReverifySsoDomainJob::class, 1);
    Queue::assertPushed(ReverifySsoDomainJob::class, fn (ReverifySsoDomainJob $job): bool => $job->connectionId === $stale->id);
    expect(recheckConnection($fresh)->domain_checked_at)->not->toBeNull();
});
