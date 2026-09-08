<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Logging\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes correlation identifiers for the request and shares them with the
 * structured logger (via Context) and downstream response headers. Accepts an
 * inbound X-Correlation-Id to stitch traces across services.
 */
class AssignRequestContext
{
    public function __construct(
        private readonly RequestContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Correlation-Id');

        if (is_string($incoming) && $incoming !== '') {
            $this->context->setCorrelationId(substr($incoming, 0, 64));
        }

        Context::add('request_id', $this->context->requestId());
        Context::add('correlation_id', $this->context->correlationId());

        $response = $next($request);

        $response->headers->set('X-Request-Id', $this->context->requestId());
        $response->headers->set('X-Correlation-Id', $this->context->correlationId());

        return $response;
    }
}
