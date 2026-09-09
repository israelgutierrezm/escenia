<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

/**
 * Outcome of a domain ownership check, agnostic of how it was performed
 * (DNS lookup, provider API, fake). Keeps the verifier contract free of
 * transport detail.
 */
final class DomainVerificationResult
{
    private function __construct(
        public readonly bool $verified,
        public readonly ?string $detail = null,
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function failure(string $detail): self
    {
        return new self(false, $detail);
    }
}
