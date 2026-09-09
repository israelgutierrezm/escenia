<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Domains;

use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\DTOs\DomainVerificationResult;
use App\Domain\Enterprise\Models\CustomDomain;

/**
 * Deterministic, network-free domain verifier for dev and tests. It "finds" the
 * TXT challenge for any hostname except those under the reserved `unverified.`
 * label, which always fail — so both paths are exercisable offline.
 */
final class FakeDomainVerifier implements DomainVerifier
{
    public function verify(CustomDomain $domain): DomainVerificationResult
    {
        if (str_starts_with($domain->hostname, 'unverified.')) {
            return DomainVerificationResult::failure('TXT challenge record not found.');
        }

        return DomainVerificationResult::success();
    }
}
