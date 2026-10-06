<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Domains;

use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\DTOs\DnsChallenge;
use App\Domain\Enterprise\DTOs\DomainVerificationResult;

/**
 * Fail-closed verifier, bound when no real verifier is configured where the fake
 * is not allowed (production). Verification is a security control — it gates SSO
 * email domains — so "not configured" must mean "never verified".
 */
final class DisabledDomainVerifier implements DomainVerifier
{
    public function verify(DnsChallenge $challenge): DomainVerificationResult
    {
        return DomainVerificationResult::failure('Domain verification is not configured in this environment.');
    }
}
