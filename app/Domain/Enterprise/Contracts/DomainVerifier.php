<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Contracts;

use App\Domain\Enterprise\DTOs\DomainVerificationResult;
use App\Domain\Enterprise\Models\CustomDomain;

/**
 * Proves that a tenant controls a custom domain. Implementations decide how
 * (DNS TXT lookup, registrar/provider API, or a deterministic fake); the domain
 * never depends on a concrete DNS/HTTP library (ADR-008).
 */
interface DomainVerifier
{
    public function verify(CustomDomain $domain): DomainVerificationResult;
}
