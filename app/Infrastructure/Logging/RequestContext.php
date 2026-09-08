<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Illuminate\Support\Str;

/**
 * Request-scoped correlation identifiers. Populated by the AssignRequestContext
 * middleware and consumed by structured logging and the audit trail so every
 * log line and audit row can be tied back to a single request/trace.
 */
class RequestContext
{
    private string $requestId;

    private string $correlationId;

    public function __construct()
    {
        $this->requestId = (string) Str::uuid();
        $this->correlationId = $this->requestId;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }
}
