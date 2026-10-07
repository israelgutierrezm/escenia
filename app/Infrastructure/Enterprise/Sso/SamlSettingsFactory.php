<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\DTOs\ServiceProviderCredentials;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use Closure;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;

/**
 * onelogin/php-saml settings for a SAML connection, always in strict mode. The
 * IdP comes from the connection's config; Escenia's SP side from its endpoints
 * and its own credentials (ADR-036). Options (config): `sign_requests` signs
 * AuthnRequests and logout messages, `encrypt_assertions` requires encrypted
 * assertions, `idp_slo_url` enables single logout. Logout messages *from* the
 * IdP must always be signed.
 */
final class SamlSettingsFactory
{
    /**
     * For validating a login Response: either the Response or its Assertion
     * must be signed (IdPs differ; the library rejects neither).
     */
    public function forLogin(SsoConnection $connection): Settings
    {
        return $this->build($connection, wantMessagesSigned: false);
    }

    /**
     * For single logout: LogoutRequest/LogoutResponse from the IdP must be signed.
     */
    public function forLogout(SsoConnection $connection): Settings
    {
        return $this->build($connection, wantMessagesSigned: true);
    }

    /**
     * SP-only settings, for publishing Escenia's metadata.
     */
    public function forMetadata(SsoConnection $connection): Settings
    {
        return new Settings([
            'strict' => true,
            'sp' => $this->serviceProvider($connection),
            'security' => $this->security($connection, wantMessagesSigned: false),
        ], true);
    }

    /**
     * @return array{entity_id: string, sso_url: string, x509_cert: string, slo_url: string|null}
     */
    public function idp(SsoConnection $connection): array
    {
        if ($connection->provider !== SsoProvider::Saml) {
            throw new SsoAuthenticationException;
        }

        $idp = [
            'entity_id' => $this->read($connection, 'idp_entity_id'),
            'sso_url' => $this->read($connection, 'idp_sso_url'),
            'x509_cert' => $this->read($connection, 'idp_x509_cert'),
            'slo_url' => $this->read($connection, 'idp_slo_url') !== '' ? $this->read($connection, 'idp_slo_url') : null,
        ];

        if ($idp['entity_id'] === '' || $idp['x509_cert'] === '' || ! self::isHttpUrl($idp['sso_url'])
            || ($idp['slo_url'] !== null && ! self::isHttpUrl($idp['slo_url']))) {
            throw new SsoAuthenticationException;
        }

        return $idp;
    }

    /**
     * The private key that signs outgoing messages, when the connection asks
     * for signing. Asking without credentials fails closed.
     */
    public function signingKey(SsoConnection $connection): ?string
    {
        if (! $this->option($connection, 'sign_requests')) {
            return null;
        }

        return $this->credentials($connection)->privateKey;
    }

    /**
     * Runs a onelogin validation with its "current URL" pinned to the origin of
     * the endpoints we advertise (derived from APP_URL — never the request's
     * Host header); the path stays the routed request path. The library's
     * overrides are reset afterwards.
     *
     * @template T
     *
     * @param  Closure(): T  $validation
     * @return T
     */
    public function withPinnedOrigin(SsoConnection $connection, Closure $validation): mixed
    {
        $url = parse_url(SamlEndpoints::acsUrl($connection));
        $origin = ($url['scheme'] ?? 'https').'://'.($url['host'] ?? '').(isset($url['port']) ? ':'.$url['port'] : '');

        Utils::setBaseURL($origin.'/');

        try {
            return $validation();
        } finally {
            Utils::setBaseURL('');
        }
    }

    public function read(SsoConnection $connection, string $key): string
    {
        $value = $connection->config[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    private function build(SsoConnection $connection, bool $wantMessagesSigned): Settings
    {
        $idp = $this->idp($connection);

        $idpSettings = [
            'entityId' => $idp['entity_id'],
            'singleSignOnService' => ['url' => $idp['sso_url'], 'binding' => Constants::BINDING_HTTP_REDIRECT],
            'x509cert' => $idp['x509_cert'],
        ];

        if ($idp['slo_url'] !== null) {
            $idpSettings['singleLogoutService'] = ['url' => $idp['slo_url'], 'binding' => Constants::BINDING_HTTP_REDIRECT];
        }

        try {
            return new Settings([
                'strict' => true,
                'debug' => false,
                'sp' => $this->serviceProvider($connection),
                'idp' => $idpSettings,
                'security' => $this->security($connection, $wantMessagesSigned),
            ]);
        } catch (Throwable) {
            throw new SsoAuthenticationException;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceProvider(SsoConnection $connection): array
    {
        $sp = [
            'entityId' => SamlEndpoints::entityId($connection),
            'assertionConsumerService' => [
                'url' => SamlEndpoints::acsUrl($connection),
                'binding' => Constants::BINDING_HTTP_POST,
            ],
            'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
        ];

        if ($this->read($connection, 'idp_slo_url') !== '') {
            $sp['singleLogoutService'] = ['url' => SamlEndpoints::sloUrl($connection), 'binding' => Constants::BINDING_HTTP_REDIRECT];
        }

        $credentials = $connection->serviceProviderCredentials();

        if ($credentials !== null) {
            $sp['x509cert'] = $credentials->certificate;
            $sp['privateKey'] = $credentials->privateKey;
        }

        return $sp;
    }

    /**
     * @return array<string, mixed>
     */
    private function security(SsoConnection $connection, bool $wantMessagesSigned): array
    {
        $sign = $this->option($connection, 'sign_requests');
        $encrypt = $this->option($connection, 'encrypt_assertions');

        if (($sign || $encrypt) && $connection->serviceProviderCredentials() === null) {
            throw new SsoAuthenticationException; // asked for, but no SP key to do it with
        }

        return [
            'authnRequestsSigned' => $sign,
            'logoutRequestSigned' => $sign,
            'logoutResponseSigned' => $sign,
            'wantMessagesSigned' => $wantMessagesSigned,
            'wantAssertionsSigned' => false,
            'wantAssertionsEncrypted' => $encrypt,
            'wantNameId' => true,
            'wantXMLValidation' => true,
            'rejectUnsolicitedResponsesWithInResponseTo' => true,
            'destinationStrictlyMatches' => true,
            'relaxDestinationValidation' => false,
            'signatureAlgorithm' => XMLSecurityKey::RSA_SHA256,
            'digestAlgorithm' => XMLSecurityDSig::SHA256,
        ];
    }

    private function credentials(SsoConnection $connection): ServiceProviderCredentials
    {
        return $connection->serviceProviderCredentials() ?? throw new SsoAuthenticationException;
    }

    private function option(SsoConnection $connection, string $key): bool
    {
        return filter_var($connection->config[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private static function isHttpUrl(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
