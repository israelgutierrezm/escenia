<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Enums\TenantRole;

/**
 * Creates an SSO connection for the current tenant. The provider `config`
 * (secrets/endpoints) is encrypted by the model cast; it is never written to
 * the audit context. The email domain starts unverified: the connection cannot
 * log anyone in until the tenant passes its DNS challenge
 * ({@see VerifySsoDomainAction}).
 */
final class CreateSsoConnectionAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function execute(
        User $actor,
        SsoProvider $provider,
        string $displayName,
        ?string $domain,
        array $config,
        TenantRole $defaultRole,
    ): SsoConnection {
        $connection = new SsoConnection([
            'provider' => $provider,
            'display_name' => $displayName,
            'config' => $config,
            'default_role' => $defaultRole,
            'is_active' => true,
        ]);
        $connection->assignDomain($domain);
        $connection->save();

        $this->audit->log('enterprise.sso.connection_created', actor: $actor, auditable: $connection, context: [
            'provider' => $provider->value,
            'domain' => $connection->domain,
            'default_role' => $defaultRole->value,
        ]);

        return $connection;
    }
}
