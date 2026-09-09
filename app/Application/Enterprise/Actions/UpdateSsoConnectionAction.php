<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;

/**
 * Applies a partial update to an SSO connection. Only the keys the caller
 * supplies are changed; `config` (if present) replaces the stored, encrypted
 * config wholesale. Secret values are never written to the audit context.
 */
final class UpdateSsoConnectionAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, SsoConnection $connection, array $attributes): SsoConnection
    {
        $connection->fill($attributes)->save();

        $this->audit->log('enterprise.sso.connection_updated', actor: $actor, auditable: $connection, context: [
            'changed' => array_values(array_diff(array_keys($attributes), ['config'])),
            'config_changed' => array_key_exists('config', $attributes),
        ]);

        return $connection->refresh();
    }
}
