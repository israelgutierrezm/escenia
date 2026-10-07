<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Contracts\SingleLogoutProvider;
use App\Domain\Enterprise\DTOs\LogoutRedirect;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OneLogin\Saml2\LogoutRequest;
use OneLogin\Saml2\LogoutResponse;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Throwable;

/**
 * SAML single logout over the HTTP-Redirect binding (ADR-036), for connections
 * with an `idp_slo_url`:
 * - SP-initiated: on logout, send the browser to the IdP with a LogoutRequest
 *   for the session the login opened; the IdP answers at our SLO endpoint.
 * - IdP-initiated: validate the IdP's LogoutRequest and answer it.
 * Messages from the IdP must be signed with a strong algorithm (SHA-1 and a
 * missing SigAlg are refused; the library would assume SHA-1); the signature is
 * checked over the original query string. onelogin reads $_GET/QUERY_STRING.
 */
final class SamlSingleLogout implements SingleLogoutProvider
{
    private const STRONG_SIGNATURES = [
        XMLSecurityKey::RSA_SHA256,
        XMLSecurityKey::RSA_SHA384,
        XMLSecurityKey::RSA_SHA512,
    ];

    public function __construct(
        private readonly SamlSettingsFactory $settings,
    ) {}

    public function start(SsoConnection $connection, array $session): ?LogoutRedirect
    {
        $nameId = self::text($session, 'name_id');

        if ($connection->provider !== SsoProvider::Saml || $nameId === '') {
            return null;
        }

        try {
            $idp = $this->settings->idp($connection);

            if ($idp['slo_url'] === null) {
                return null;
            }

            $request = new LogoutRequest(
                $this->settings->forLogout($connection),
                null,
                $nameId,
                self::text($session, 'session_index') !== '' ? self::text($session, 'session_index') : null,
                self::text($session, 'name_id_format') !== '' ? self::text($session, 'name_id_format') : null,
            );

            return new LogoutRedirect(
                SamlRedirectBinding::url($idp['slo_url'], 'SAMLRequest', $request->getRequest(), null, $this->settings->signingKey($connection)),
                (string) $request->id,
            );
        } catch (Throwable $e) {
            // The local logout still happens; only the IdP session is left open.
            Log::notice('SAML single logout could not start.', ['connection' => $connection->ulid, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Validates an IdP-initiated LogoutRequest and builds the answer.
     *
     * @return array{name_id: string, response_url: string}
     *
     * @throws SsoAuthenticationException
     */
    public function answer(SsoConnection $connection, Request $request): array
    {
        $this->assertStrongSignature($request);
        $idp = $this->settings->idp($connection);

        if ($idp['slo_url'] === null) {
            throw new SsoAuthenticationException;
        }

        $settings = $this->settings->forLogout($connection);

        try {
            $logoutRequest = new LogoutRequest($settings, (string) $request->query('SAMLRequest'));
            $valid = $this->settings->withPinnedOrigin($connection, static fn (): bool => $logoutRequest->isValid(true));

            if (! $valid) {
                throw new SsoAuthenticationException((string) $logoutRequest->getError());
            }

            $xml = $logoutRequest->getXML();
            $nameId = LogoutRequest::getNameId($xml, $settings->getSPkey());
            $requestId = LogoutRequest::getID($xml);

            $response = new LogoutResponse($settings);
            $response->build($requestId);
        } catch (Throwable $e) {
            Log::notice('SAML logout request rejected.', ['connection' => $connection->ulid, 'error' => $e->getMessage()]);

            throw new SsoAuthenticationException;
        }

        $relayState = $request->query('RelayState');

        return [
            'name_id' => $nameId,
            'response_url' => SamlRedirectBinding::url(
                $idp['slo_url'],
                'SAMLResponse',
                $response->getResponse(),
                is_string($relayState) ? $relayState : null,
                $this->settings->signingKey($connection),
            ),
        ];
    }

    /**
     * Whether the IdP's LogoutResponse validly answers our LogoutRequest.
     */
    public function confirms(SsoConnection $connection, Request $request, string $requestId): bool
    {
        try {
            $this->assertStrongSignature($request);
            $response = new LogoutResponse($this->settings->forLogout($connection), (string) $request->query('SAMLResponse'));

            return $this->settings->withPinnedOrigin($connection, static fn (): bool => $response->isValid($requestId, true));
        } catch (Throwable $e) {
            Log::notice('SAML logout response rejected.', ['connection' => $connection->ulid, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function assertStrongSignature(Request $request): void
    {
        if (! in_array($request->query('SigAlg'), self::STRONG_SIGNATURES, true)) {
            throw new SsoAuthenticationException;
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private static function text(array $session, string $key): string
    {
        $value = $session[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
