<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Domains;

use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\DTOs\DomainVerificationResult;
use App\Domain\Enterprise\Models\CustomDomain;

/**
 * Real TXT-record verifier. Looks up `_escenia-challenge.<hostname>` and checks
 * the tenant published the expected token. Not integration-tested in this
 * environment (no live DNS); the default provider is the fake (see technical
 * debt). The domain never sees the resolver — only a {@see DomainVerificationResult}.
 */
final class DnsDomainVerifier implements DomainVerifier
{
    public function verify(CustomDomain $domain): DomainVerificationResult
    {
        $challenge = $domain->dnsChallenge();

        /** @var list<array<string, mixed>> $records */
        $records = @dns_get_record($challenge['name'], DNS_TXT) ?: [];

        foreach ($records as $record) {
            $txt = isset($record['txt']) ? (string) $record['txt'] : '';

            if ($txt !== '' && hash_equals($domain->verification_token, $txt)) {
                return DomainVerificationResult::success();
            }
        }

        return DomainVerificationResult::failure('Expected TXT record not found for '.$challenge['name'].'.');
    }
}
