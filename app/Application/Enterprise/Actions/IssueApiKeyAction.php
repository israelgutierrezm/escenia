<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Application\Enterprise\DTOs\IssuedApiKey;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Models\ApiKey;
use App\Domain\Enterprise\Support\ApiKeyToken;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;

/**
 * Mints a programmatic API key for the current tenant. The raw key is generated
 * here, returned once via {@see IssuedApiKey}, and never persisted — only its
 * SHA-256 hash is stored. The audit entry records the prefix/scopes, never the
 * secret.
 */
final class IssueApiKeyAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $scopes
     */
    public function execute(User $actor, string $name, array $scopes, ?Carbon $expiresAt = null): IssuedApiKey
    {
        $token = ApiKeyToken::generate();

        $apiKey = ApiKey::query()->create([
            'created_by' => $actor->getKey(),
            'name' => $name,
            'prefix' => $token->prefix,
            'token_hash' => $token->hash,
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        $this->audit->log('enterprise.api_key.created', actor: $actor, auditable: $apiKey, context: [
            'name' => $name,
            'prefix' => $token->prefix,
            'scopes' => $scopes,
        ]);

        return new IssuedApiKey($apiKey, $token->raw);
    }
}
