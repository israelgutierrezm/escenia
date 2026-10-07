<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use Throwable;

/**
 * SAML 2.0 Web Browser SSO, SP-initiated: an HTTP-Redirect AuthnRequest out,
 * an HTTP-POST Response back to the ACS. The AuthnRequest ID derives from the
 * login's nonce, so only a Response to *this* attempt is accepted
 * (InResponseTo). onelogin/php-saml validates it in strict mode — signature
 * against the IdP certificate (on the Response or the Assertion), schema,
 * InResponseTo, audience, issuer, destination/recipient and time conditions —
 * and SHA-1 signatures/digests are refused before that. The library stays
 * behind this adapter (ADR-008); no metadata is ever fetched from the IdP.
 */
final class SamlIdentityProvider implements IdentityProvider
{
    private const EMAIL_ATTRIBUTES = [
        'email',
        'mail',
        'emailaddress',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'urn:oid:0.9.2342.19200300.100.1.3',
    ];

    private const NAME_ATTRIBUTES = [
        'name',
        'displayname',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        'http://schemas.microsoft.com/identity/claims/displayname',
        'urn:oid:2.16.840.1.113730.3.1.241',
    ];

    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string
    {
        $idp = $this->idp($connection);

        $authnRequest = sprintf(
            '<samlp:AuthnRequest xmlns:samlp="%s" xmlns:saml="%s" ID="%s" Version="2.0" IssueInstant="%s" '
            .'Destination="%s" ProtocolBinding="%s" AssertionConsumerServiceURL="%s">'
            .'<saml:Issuer>%s</saml:Issuer>'
            .'<samlp:NameIDPolicy Format="%s" AllowCreate="true"/>'
            .'</samlp:AuthnRequest>',
            Constants::NS_SAMLP,
            Constants::NS_SAML,
            self::requestId($request->nonce),
            gmdate('Y-m-d\TH:i:s\Z'),
            e($idp['sso_url']),
            Constants::BINDING_HTTP_POST,
            e(SamlEndpoints::acsUrl($connection)),
            e(SamlEndpoints::entityId($connection)),
            Constants::NAMEID_UNSPECIFIED,
        );

        $query = http_build_query([
            'SAMLRequest' => base64_encode((string) gzdeflate($authnRequest)),
            'RelayState' => $request->state,
        ]);

        return $idp['sso_url'].(str_contains($idp['sso_url'], '?') ? '&' : '?').$query;
    }

    /**
     * `code` carries the base64 SAMLResponse posted to the ACS.
     */
    public function verifyCallback(SsoConnection $connection, SsoCallback $callback): ExternalIdentity
    {
        $settings = $this->settings($connection);

        if (! $this->usesStrongAlgorithms($callback->code)) {
            throw $this->rejected($connection, 'SHA-1 signature or digest.');
        }

        // onelogin compares Destination/Recipient with "the current URL". Pin its
        // scheme/host/port to the origin of the ACS URL we advertise (not the
        // Host header); the path is the routed request path. An empty base URL
        // resets the library's overrides afterwards.
        $acs = parse_url(SamlEndpoints::acsUrl($connection));
        $origin = ($acs['scheme'] ?? 'https').'://'.($acs['host'] ?? '').(isset($acs['port']) ? ':'.$acs['port'] : '');

        Utils::setBaseURL($origin.'/');

        try {
            $response = new Response($settings, $callback->code);
            $valid = $response->isValid(self::requestId($callback->nonce));
        } catch (Throwable $e) {
            throw $this->rejected($connection, $e->getMessage());
        } finally {
            Utils::setBaseURL('');
        }

        if (! $valid) {
            throw $this->rejected($connection, (string) $response->getError());
        }

        try {
            $attributes = self::lowercaseKeys($response->getAttributes());
            $nameId = trim($response->getNameId());
        } catch (Throwable $e) {
            throw $this->rejected($connection, $e->getMessage());
        }

        $subjectAttribute = $this->read($connection, 'subject_attribute');
        $subject = $subjectAttribute !== '' ? self::first($attributes, [$subjectAttribute]) : $nameId;

        $emailAttribute = $this->read($connection, 'email_attribute');
        $email = Str::lower(self::first($attributes, $emailAttribute !== '' ? [$emailAttribute] : self::EMAIL_ATTRIBUTES));

        if ($email === '' && filter_var($nameId, FILTER_VALIDATE_EMAIL) !== false) {
            $email = Str::lower($nameId);
        }

        if ($subject === '' || $email === '') {
            throw $this->rejected($connection, 'No subject or email in the assertion.');
        }

        $nameAttribute = $this->read($connection, 'name_attribute');
        $name = self::first($attributes, $nameAttribute !== '' ? [$nameAttribute] : self::NAME_ATTRIBUTES);

        return new ExternalIdentity(
            subject: $subject,
            email: $email,
            name: $name !== '' ? Str::limit($name, 255, '') : $email,
        );
    }

    /**
     * Escenia's SP metadata for this connection (for the IdP administrator).
     */
    public function serviceProviderMetadata(SsoConnection $connection): string
    {
        $settings = new Settings(['strict' => true, 'sp' => $this->serviceProvider($connection)], true);

        return $settings->getSPMetadata();
    }

    /**
     * The ID of the AuthnRequest of a login: an NCName derived from its nonce.
     */
    private static function requestId(string $nonce): string
    {
        return '_'.$nonce;
    }

    private function settings(SsoConnection $connection): Settings
    {
        $idp = $this->idp($connection);

        try {
            return new Settings([
                'strict' => true,
                'debug' => false,
                'sp' => $this->serviceProvider($connection),
                'idp' => [
                    'entityId' => $idp['entity_id'],
                    'singleSignOnService' => ['url' => $idp['sso_url'], 'binding' => Constants::BINDING_HTTP_REDIRECT],
                    'x509cert' => $idp['x509_cert'],
                ],
                'security' => [
                    // IdPs differ on what they sign; the library rejects a Response
                    // where neither the message nor the assertion is signed.
                    'wantMessagesSigned' => false,
                    'wantAssertionsSigned' => false,
                    'wantNameId' => true,
                    'wantXMLValidation' => true,
                    'rejectUnsolicitedResponsesWithInResponseTo' => true,
                    'destinationStrictlyMatches' => true,
                    'relaxDestinationValidation' => false,
                ],
            ]);
        } catch (Throwable $e) {
            throw $this->rejected($connection, 'Invalid SAML settings: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceProvider(SsoConnection $connection): array
    {
        return [
            'entityId' => SamlEndpoints::entityId($connection),
            'assertionConsumerService' => [
                'url' => SamlEndpoints::acsUrl($connection),
                'binding' => Constants::BINDING_HTTP_POST,
            ],
            'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,
        ];
    }

    /**
     * @return array{entity_id: string, sso_url: string, x509_cert: string}
     */
    private function idp(SsoConnection $connection): array
    {
        if ($connection->provider !== SsoProvider::Saml) {
            throw new SsoAuthenticationException;
        }

        $idp = [
            'entity_id' => $this->read($connection, 'idp_entity_id'),
            'sso_url' => $this->read($connection, 'idp_sso_url'),
            'x509_cert' => $this->read($connection, 'idp_x509_cert'),
        ];

        $scheme = strtolower((string) parse_url($idp['sso_url'], PHP_URL_SCHEME));

        if ($idp['entity_id'] === '' || $idp['x509_cert'] === '' || ! in_array($scheme, ['http', 'https'], true)) {
            throw new SsoAuthenticationException;
        }

        return $idp;
    }

    private function read(SsoConnection $connection, string $key): string
    {
        $value = $connection->config[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Refuses SHA-1 signature and digest algorithms, which the library would
     * otherwise accept. Parsed with the library's loader (no DOCTYPE → no XXE).
     */
    private function usesStrongAlgorithms(string $samlResponse): bool
    {
        $xml = base64_decode($samlResponse, true);
        $document = new DOMDocument;

        try {
            if ($xml === false || Utils::loadXML($document, $xml) === false) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $algorithms = $xpath->query('//ds:SignatureMethod/@Algorithm | //ds:DigestMethod/@Algorithm');

        foreach ($algorithms !== false ? $algorithms : [] as $algorithm) {
            if (str_ends_with(strtolower((string) $algorithm->nodeValue), 'sha1')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<string, mixed>
     */
    private static function lowercaseKeys(array $attributes): array
    {
        $lowered = [];

        foreach ($attributes as $name => $values) {
            $lowered[strtolower((string) $name)] = $values;
        }

        return $lowered;
    }

    /**
     * The first non-empty value among the given attribute names.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $names
     */
    private static function first(array $attributes, array $names): string
    {
        foreach ($names as $name) {
            $values = $attributes[strtolower($name)] ?? null;
            $value = is_array($values) ? ($values[0] ?? null) : null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function rejected(SsoConnection $connection, string $reason): SsoAuthenticationException
    {
        Log::notice('SAML response rejected.', ['connection' => $connection->ulid, 'reason' => $reason]);

        return new SsoAuthenticationException;
    }
}
