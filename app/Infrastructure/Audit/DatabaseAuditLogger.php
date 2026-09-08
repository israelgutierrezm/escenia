<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Logging\RequestContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

final class DatabaseAuditLogger implements AuditLogger
{
    /**
     * Keys whose values must never be written to the audit trail.
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'token', 'secret', 'authorization',
        'api_key', 'apikey', 'access_token', 'refresh_token', 'stream_key',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RequestContext $requestContext,
    ) {}

    public function log(
        string $action,
        ?User $actor = null,
        ?Tenant $tenant = null,
        ?Model $auditable = null,
        array $context = [],
    ): AuditLog {
        $actor ??= Auth::user() instanceof User ? Auth::user() : null;

        return AuditLog::create([
            'tenant_id' => $tenant?->getKey() ?? $this->tenantContext->id(),
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actor?->getAttribute('email') ?? 'system',
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'context' => $this->sanitize($context),
            'ip_address' => Request::ip(),
            'request_id' => $this->requestContext->requestId(),
            'correlation_id' => $this->requestContext->correlationId(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
