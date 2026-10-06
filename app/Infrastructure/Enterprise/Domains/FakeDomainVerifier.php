<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Domains;

use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\DTOs\DnsChallenge;
use App\Domain\Enterprise\DTOs\DomainVerificationResult;
use Illuminate\Support\Str;

/**
 * Deterministic, network-free domain verifier for dev and tests. It "finds" the
 * TXT challenge for any domain except those under the reserved `unverified.`
 * label, which always fail — so both paths are exercisable offline. Never bound
 * in production (it would approve any domain): see EnterpriseServiceProvider.
 */
final class FakeDomainVerifier implements DomainVerifier
{
    public function verify(DnsChallenge $challenge): DomainVerificationResult
    {
        // The challenge name is `_escenia-<purpose>.<domain>`.
        if (str_starts_with(Str::after($challenge->name, '.'), 'unverified.')) {
            return DomainVerificationResult::failure('TXT challenge record not found.');
        }

        return DomainVerificationResult::success();
    }
}
