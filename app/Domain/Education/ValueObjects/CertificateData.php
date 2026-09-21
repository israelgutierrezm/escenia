<?php

declare(strict_types=1);

namespace App\Domain\Education\ValueObjects;

/**
 * The gateway-agnostic data a renderer needs to draw a certificate. Keeps the
 * PDF engine out of the domain: the renderer receives only these primitives,
 * never an Eloquent model.
 */
final class CertificateData
{
    public function __construct(
        public readonly string $recipientName,
        public readonly string $eventTitle,
        public readonly string $code,
        public readonly string $issuedOnLabel,
        public readonly string $accentColor,
        public readonly ?string $verifyUrl,
    ) {}
}
