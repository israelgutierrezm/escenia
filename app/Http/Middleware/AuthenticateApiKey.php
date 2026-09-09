<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Enterprise\Models\ApiKey;
use App\Domain\Enterprise\Support\ApiKeyToken;
use App\Domain\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a programmatic request from an API key presented as a bearer
 * token (`Authorization: Bearer esk_...`) or the `X-Api-Key` header. The key is
 * the credential; only its SHA-256 hash is stored (never in the URL, never
 * logged).
 *
 * The key is looked up unscoped by hash, then the tenant context (and Spatie
 * permission team) is established from the key's own tenant — so downstream
 * queries are correctly scoped without ever trusting a client-supplied tenant
 * id. The resolved key is stashed on the request for scope checks.
 */
class AuthenticateApiKey
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $raw = $this->extractToken($request);

        if ($raw === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'API key required.');
        }

        $apiKey = ApiKey::query()
            ->withoutGlobalScopes()
            ->where('token_hash', ApiKeyToken::hash($raw))
            ->first();

        if ($apiKey === null || ! $apiKey->isUsable()) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid API key.');
        }

        $this->tenantContext->setTenant($apiKey->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($apiKey->tenant->getKey());
        Context::add('tenant_id', $apiKey->tenant->ulid);
        Context::add('api_key_id', $apiKey->ulid);

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && str_starts_with($bearer, 'esk_')) {
            return $bearer;
        }

        $header = $request->headers->get('X-Api-Key');

        return is_string($header) && $header !== '' ? $header : null;
    }
}
