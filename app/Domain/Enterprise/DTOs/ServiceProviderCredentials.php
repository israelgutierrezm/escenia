<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

use SensitiveParameter;

/**
 * Escenia's own credentials as a SAML service provider for one connection: a
 * certificate the IdP can trust (published in the SP metadata) and the private
 * key that signs requests and decrypts assertions. PEM encoded.
 */
final class ServiceProviderCredentials
{
    public function __construct(
        public readonly string $certificate,
        #[SensitiveParameter]
        public readonly string $privateKey,
    ) {}
}
