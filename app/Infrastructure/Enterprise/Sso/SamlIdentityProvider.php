<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\DTOs\ExternalIdentity;
use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Utils;
use Throwable;

/**
 * SAML 2.0 Web Browser SSO, SP-initiated: an HTTP-Redirect AuthnRequest out
 * (signed when the connection asks), an HTTP-POST Response back to the ACS
 * (assertions may be — or be required to be — encrypted to Escenia's SP
 * certificate). The AuthnRequest ID derives from the login's nonce, so only a
 * Response to *this* attempt is accepted (InResponseTo). onelogin/php-saml
 * validates it in strict mode — signature against the IdP certificate (on the
 * Response or the Assertion), schema, InResponseTo, audience, issuer,
 * destination/recipient and time conditions — and weak algorithms (SHA-1
 * signatures/digests, RSA-1.5 key transport) are refused before that, also
 * inside decrypted assertions. The library stays behind this adapter (ADR-008).
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

    public function __construct(
        private readonly SamlSettingsFactory $settings,
    ) {}

    public function authorizationUrl(SsoConnection $connection, SsoAuthorizationRequest $request): string
    {
        $idp = $this->settings->idp($connection);

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

        return SamlRedirectBinding::url(
            $idp['sso_url'],
            'SAMLRequest',
            base64_encode((string) gzdeflate($authnRequest)),
            $request->state,
            $this->settings->signingKey($connection),
        );
    }

    /**
     * `code` carries the base64 SAMLResponse posted to the ACS.
     */
    public function verifyCallback(SsoConnection $connection, SsoCallback $callback): ExternalIdentity
    {
        $settings = $this->settings->forLogin($connection);
        $original = self::load((string) base64_decode($callback->code, true));

        if ($original === null || ! self::usesStrongAlgorithms($original)) {
            throw $this->rejected($connection, 'Unreadable response or weak algorithm.');
        }

        try {
            [$response, $valid] = $this->settings->withPinnedOrigin($connection, function () use ($settings, $callback): array {
                $response = new Response($settings, $callback->code); // decrypts an EncryptedAssertion

                // The decrypted assertion carries its own signature: same rule.
                $valid = self::usesStrongAlgorithms($response->getXMLDocument())
                    && $response->isValid(self::requestId($callback->nonce));

                return [$response, $valid];
            });
        } catch (Throwable $e) {
            throw $this->rejected($connection, $e->getMessage());
        }

        if (! $valid) {
            throw $this->rejected($connection, (string) $response->getError());
        }

        try {
            $attributes = self::lowercaseKeys($response->getAttributes());
            $nameId = trim($response->getNameId());
            $session = array_filter([
                'name_id' => $nameId,
                'name_id_format' => (string) $response->getNameIdFormat(),
                'session_index' => (string) $response->getSessionIndex(),
            ], static fn (string $value): bool => $value !== '');
        } catch (Throwable $e) {
            throw $this->rejected($connection, $e->getMessage());
        }

        $subjectAttribute = $this->settings->read($connection, 'subject_attribute');
        $subject = $subjectAttribute !== '' ? self::first($attributes, [$subjectAttribute]) : $nameId;

        $emailAttribute = $this->settings->read($connection, 'email_attribute');
        $email = Str::lower(self::first($attributes, $emailAttribute !== '' ? [$emailAttribute] : self::EMAIL_ATTRIBUTES));

        if ($email === '' && filter_var($nameId, FILTER_VALIDATE_EMAIL) !== false) {
            $email = Str::lower($nameId);
        }

        if ($subject === '' || $email === '') {
            throw $this->rejected($connection, 'No subject or email in the assertion.');
        }

        $nameAttribute = $this->settings->read($connection, 'name_attribute');
        $name = self::first($attributes, $nameAttribute !== '' ? [$nameAttribute] : self::NAME_ATTRIBUTES);

        return new ExternalIdentity(
            subject: $subject,
            email: $email,
            name: $name !== '' ? Str::limit($name, 255, '') : $email,
            session: $session,
        );
    }

    /**
     * Escenia's SP metadata for this connection (for the IdP administrator),
     * with its signing and encryption certificate.
     */
    public function serviceProviderMetadata(SsoConnection $connection): string
    {
        return $this->settings->forMetadata($connection)->getSPMetadata(true);
    }

    /**
     * The ID of the AuthnRequest of a login: an NCName derived from its nonce.
     */
    private static function requestId(string $nonce): string
    {
        return '_'.$nonce;
    }

    /**
     * Parsed with the library's loader, which refuses DOCTYPEs (no XXE).
     */
    private static function load(string $xml): ?DOMDocument
    {
        $document = new DOMDocument;

        try {
            return $xml !== '' && Utils::loadXML($document, $xml) !== false ? $document : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Refuses SHA-1 signatures and digests and RSA-1.5 key transport, which the
     * library would otherwise accept. (RSA-OAEP's own SHA-1 digest is fine and
     * not inspected: it sits outside the signatures.)
     */
    private static function usesStrongAlgorithms(DOMDocument $document): bool
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xenc', 'http://www.w3.org/2001/04/xmlenc#');

        $algorithms = $xpath->query(
            '//ds:SignedInfo/ds:SignatureMethod/@Algorithm'
            .' | //ds:SignedInfo/ds:Reference/ds:DigestMethod/@Algorithm'
            .' | //xenc:EncryptedKey/xenc:EncryptionMethod/@Algorithm',
        );

        foreach ($algorithms !== false ? $algorithms : [] as $algorithm) {
            $uri = strtolower((string) $algorithm->nodeValue);

            if (str_ends_with($uri, 'sha1') || str_ends_with($uri, 'rsa-1_5')) {
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
