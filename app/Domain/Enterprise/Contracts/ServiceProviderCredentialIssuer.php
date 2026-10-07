<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Contracts;

use App\Domain\Enterprise\DTOs\ServiceProviderCredentials;

/**
 * Issues a fresh key pair and self-signed certificate for a SAML connection's
 * service provider (ADR-036). The domain never sees the crypto library.
 */
interface ServiceProviderCredentialIssuer
{
    public function issue(string $commonName): ServiceProviderCredentials;
}
