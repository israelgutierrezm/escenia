<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * SAML HTTP-Redirect binding (SAML Bindings §3.4): the message travels
 * deflated + base64 in the query string, and — when signing — the signature
 * covers that exact query (`SAMLRequest|SAMLResponse`, `RelayState`, `SigAlg`,
 * in that order) and is appended as `Signature`. We sign precisely what we send.
 */
final class SamlRedirectBinding
{
    public const SIGNATURE_ALGORITHM = XMLSecurityKey::RSA_SHA256;

    /**
     * @param  string  $type  `SAMLRequest` or `SAMLResponse`
     * @param  string  $message  the deflated + base64 message
     */
    public static function url(string $endpoint, string $type, string $message, ?string $relayState, ?string $privateKey): string
    {
        $query = $type.'='.urlencode($message);

        if ($relayState !== null) {
            $query .= '&RelayState='.urlencode($relayState);
        }

        if ($privateKey !== null) {
            $query .= '&SigAlg='.urlencode(self::SIGNATURE_ALGORITHM);

            $key = new XMLSecurityKey(self::SIGNATURE_ALGORITHM, ['type' => 'private']);
            $key->loadKey($privateKey);
            $query .= '&Signature='.urlencode(base64_encode((string) $key->signData($query)));
        }

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query;
    }
}
