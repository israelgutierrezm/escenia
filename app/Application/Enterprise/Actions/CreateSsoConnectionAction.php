<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Support\Str;

/**
 * Creates an SSO connection for the current tenant. The provider `config`
 * (secrets/endpoints) is encrypted by the model cast; it is never written to
 * the audit context.
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
        $connection = SsoConnection::query()->create([
            'provider' => $provider,
            'display_name' => $displayName,
            'domain' => $domain !== null ? Str::lower($domain) : null,
            'config' => $config,
            'default_role' => $defaultRole,
            'is_active' => true,
        ]);

        $this->audit->log('enterprise.sso.connection_created', actor: $actor, auditable: $connection, context: [
            'provider' => $provider->value,
            'domain' => $connection->domain,
            'default_role' => $defaultRole->value,
        ]);

        return $connection;
    }
}
