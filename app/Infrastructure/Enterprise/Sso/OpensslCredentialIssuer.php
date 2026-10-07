<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\ServiceProviderCredentialIssuer;
use App\Domain\Enterprise\DTOs\ServiceProviderCredentials;
use OpenSSLCertificateSigningRequest;
use RuntimeException;

/**
 * RSA-2048 key + self-signed certificate (10 years; SAML trusts the pinned
 * certificate, not a CA chain) via ext-openssl, with Escenia's own minimal
 * OpenSSL config so it also works where PHP ships none (Windows).
 */
final class OpensslCredentialIssuer implements ServiceProviderCredentialIssuer
{
    public function issue(string $commonName): ServiceProviderCredentials
    {
        $options = ['config' => resource_path('openssl/escenia.cnf'), 'digest_alg' => 'sha256'];

        $key = openssl_pkey_new($options + ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw $this->failed();
        }

        $request = openssl_csr_new(['commonName' => $commonName], $key, $options);
        $certificate = $request instanceof OpenSSLCertificateSigningRequest
            ? openssl_csr_sign($request, null, $key, 3650, $options, random_int(1, PHP_INT_MAX))
            : false;

        if ($certificate === false
            || ! openssl_x509_export($certificate, $certificatePem)
            || ! openssl_pkey_export($key, $privateKeyPem, null, $options)) {
            throw $this->failed();
        }

        return new ServiceProviderCredentials($certificatePem, $privateKeyPem);
    }

    private function failed(): RuntimeException
    {
        return new RuntimeException('Could not issue SAML service-provider credentials: '.openssl_error_string());
    }
}
