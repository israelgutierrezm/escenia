<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Application\Enterprise\DTOs\SsoLoginResult;
use App\Application\Enterprise\SsoLoginAttempts;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Enterprise\Models\SsoIdentity;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Completes an SSO login and provisions the user into the connection's tenant
 * (just-in-time). The callback must redeem an attempt this server issued — same
 * connection, same browser, once (ADR-034). The identity must belong to the
 * connection's verified email domain: the IdP is tenant-configured, so it never
 * vouches for anyone else.
 *
 * The user is resolved by the IdP subject first (ADR-035): a known subject logs
 * into the account it is linked to; a new subject links by email — unless that
 * account is already linked to a *different* subject on this connection, i.e.
 * the address was reassigned at the IdP, which is refused. A tenant membership
 * with the connection's default role is created on first login; an existing
 * member keeps their role. Establishing the HTTP session is the controller's job.
 */
final class CompleteSsoLoginAction
{
    public function __construct(
        private readonly IdentityProvider $identityProvider,
        private readonly SsoLoginAttempts $attempts,
        private readonly TenantContext $tenantContext,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, string>  $hints  untrusted extras only the dev/test fake reads
     */
    public function execute(SsoConnection $connection, string $state, ?string $binding, string $code, array $hints = []): SsoLoginResult
    {
        if (! $connection->is_active) {
            throw new SsoAuthenticationException;
        }

        $attempt = $this->attempts->claim($state, $connection, $binding)
            ?? throw $this->rejected($connection, 'invalid_state');

        $identity = $this->identityProvider->verifyCallback($connection, new SsoCallback(
            code: $code,
            redirectUri: $attempt->redirectUri,
            nonce: $attempt->nonce,
            codeVerifier: $attempt->codeVerifier,
            hints: $hints,
        ));

        if (filter_var($identity->email, FILTER_VALIDATE_EMAIL) === false || ! $connection->vouchesFor($identity->email)) {
            $this->refuse($connection, $identity, $connection->hasVerifiedDomain() ? 'email_outside_domain' : 'domain_not_verified');
        }

        $link = SsoIdentity::query()
            ->withoutGlobalScopes()
            ->where('sso_connection_id', $connection->id)
            ->where('subject', $identity->subject)
            ->first();

        if ($link === null && $this->linkedToAnotherSubject($connection, $identity->email)) {
            $this->refuse($connection, $identity, 'subject_mismatch');
        }

        $tenant = $connection->tenant;

        $user = DB::transaction(fn (): User => $this->tenantContext->runFor($tenant, function () use ($connection, $tenant, $identity, $link): User {
            $user = $link !== null ? $link->user : User::query()->where('email', $identity->email)->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $identity->name,
                    'email' => $identity->email,
                    'password' => Str::random(64), // unusable; SSO users authenticate via the IdP
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $link ??= SsoIdentity::create([
                'tenant_id' => $tenant->getKey(),
                'sso_connection_id' => $connection->getKey(),
                'user_id' => $user->getKey(),
                'subject' => $identity->subject,
            ]);
            $link->forceFill(['last_login_at' => now()])->save();

            $isNewMember = $user->tenantMembershipFor($tenant) === null;

            if ($isNewMember) {
                TenantMembership::create([
                    'tenant_id' => $tenant->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => $connection->default_role,
                    'status' => MembershipStatus::Active,
                    'joined_at' => now(),
                ]);
            }

            // Mirror the membership role to a Spatie assignment scoped to this
            // tenant's team (ADR-011), only for a freshly provisioned member.
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

            if ($isNewMember) {
                $user->assignRole($connection->default_role->value);
            }

            $this->audit->log('enterprise.sso.login', actor: $user, tenant: $tenant, auditable: $connection, context: [
                'provider' => $connection->provider->value,
                'subject' => $identity->subject,
                'provisioned' => $isNewMember,
            ]);

            return $user;
        }));

        return new SsoLoginResult($user, $identity);
    }

    /**
     * Whether the account behind this email already answers to a different IdP
     * subject on this connection.
     */
    private function linkedToAnotherSubject(SsoConnection $connection, string $email): bool
    {
        $user = User::query()->where('email', $email)->first();

        return $user !== null && SsoIdentity::query()
            ->withoutGlobalScopes()
            ->where('sso_connection_id', $connection->id)
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /**
     * Audits a refused identity on the connection's tenant (outside any
     * transaction, so the record survives), then fails generically.
     */
    private function refuse(SsoConnection $connection, ExternalIdentity $identity, string $reason): never
    {
        $this->audit->log('enterprise.sso.login_rejected', tenant: $connection->tenant, auditable: $connection, context: [
            'reason' => $reason,
            'email_domain' => Str::lower(Str::afterLast($identity->email, '@')),
        ]);

        throw $this->rejected($connection, $reason);
    }

    /**
     * The caller only ever sees the generic failure; the reason goes to the log.
     */
    private function rejected(SsoConnection $connection, string $reason): SsoAuthenticationException
    {
        Log::notice('SSO login rejected.', ['connection' => $connection->ulid, 'reason' => $reason]);

        return new SsoAuthenticationException;
    }
}
