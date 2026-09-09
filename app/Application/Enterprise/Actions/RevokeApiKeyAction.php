<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Models\ApiKey;
use App\Domain\Identity\Models\User;

/**
 * Revokes an API key. Idempotent: an already-revoked key keeps its original
 * revocation timestamp. After revocation the key can no longer authenticate.
 */
final class RevokeApiKeyAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, ApiKey $apiKey): ApiKey
    {
        if ($apiKey->revoked_at === null) {
            $apiKey->update(['revoked_at' => now()]);
        }

        $this->audit->log('enterprise.api_key.revoked', actor: $actor, auditable: $apiKey, context: [
            'prefix' => $apiKey->prefix,
        ]);

        return $apiKey->refresh();
    }
}
