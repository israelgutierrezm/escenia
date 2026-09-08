<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Registration\Context\AttendeeContext;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Tenancy\Context\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an attendee from the join token presented in the
 * X-Attendee-Token header. The token is the credential; only its SHA-256 hash
 * is stored (never in the URL, never logged).
 *
 * The attendee is looked up unscoped by token hash, then the tenant context is
 * established from the attendee's own tenant — so every subsequent tenant-owned
 * query is correctly scoped without ever trusting a client-supplied tenant id.
 */
class ResolveAttendee
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AttendeeContext $attendeeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->headers->get('X-Attendee-Token');

        if (! is_string($token) || $token === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Attendee token required.');
        }

        $attendee = Attendee::query()
            ->withoutGlobalScopes()
            ->where('join_token_hash', hash('sha256', $token))
            ->first();

        if ($attendee === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid attendee token.');
        }

        $this->attendeeContext->setAttendee($attendee);
        $this->tenantContext->setTenant($attendee->tenant);
        Context::add('tenant_id', $attendee->tenant->ulid);
        Context::add('attendee_id', $attendee->ulid);

        return $next($request);
    }
}
