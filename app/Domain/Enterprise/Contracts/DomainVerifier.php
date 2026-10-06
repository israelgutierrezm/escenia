<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Contracts;

use App\Domain\Enterprise\DTOs\DnsChallenge;
use App\Domain\Enterprise\DTOs\DomainVerificationResult;

/**
 * Proves that a tenant controls a domain by checking a DNS TXT challenge (used
 * by custom domains and SSO email domains). Implementations decide how (DNS
 * lookup, registrar/provider API, or a deterministic fake); the domain never
 * depends on a concrete DNS/HTTP library (ADR-008).
 */
interface DomainVerifier
{
    public function verify(DnsChallenge $challenge): DomainVerificationResult;
}
