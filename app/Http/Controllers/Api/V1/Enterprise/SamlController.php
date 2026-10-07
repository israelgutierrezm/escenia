<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise;

use App\Application\Enterprise\Actions\CompleteSsoLoginAction;
use App\Application\Enterprise\SsoLoginAttempts;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Http\Controllers\Controller;
use App\Infrastructure\Enterprise\Sso\SamlIdentityProvider;
use App\Infrastructure\Enterprise\Sso\SamlSingleLogout;
use App\Rules\FirstPartyUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SAML service-provider endpoints. `acs` is where the IdP's browser POST lands
 * (a session route, exempt from CSRF — it carries a signed SAMLResponse, and the
 * login is bound to the browser by the cookie and to the attempt by
 * RelayState); it completes the login, remembers the IdP session for single
 * logout and sends the browser back to the SPA it started from. `slo` is the
 * single logout service (ADR-036): it answers IdP-initiated LogoutRequests and
 * receives the IdP's answer to ours. `metadata` publishes Escenia's SP metadata
 * for the IdP admin.
 */
class SamlController extends Controller
{
    /**
     * Session key for the IdP session a SAML login opened (single logout).
     */
    public const SESSION_KEY = 'sso.saml';

    /**
     * Session key for a pending SP-initiated logout: our request ID and where to go after.
     */
    public const LOGOUT_KEY = 'sso.saml_logout';

    public function acs(Request $request, CompleteSsoLoginAction $action, SsoLoginAttempts $attempts, string $connection): Response
    {
        $model = $this->resolveSaml($connection);
        $state = (string) $request->input('RelayState', '');
        $returnUrl = $attempts->returnUrl($state);
        $binding = $request->cookie(SsoController::BINDING_COOKIE);

        try {
            $result = $action->execute(
                $model,
                $state,
                is_string($binding) ? $binding : null,
                (string) $request->input('SAMLResponse', ''),
            );
        } catch (SsoAuthenticationException) {
            return $this->backToApp($returnUrl, ['error' => 'sso_failed']);
        }

        Auth::guard('web')->login($result->user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, ['connection' => $model->ulid] + $result->identity->session);

        return $this->backToApp($returnUrl, ['status' => 'ok'])
            ->withoutCookie(SsoController::BINDING_COOKIE, SsoController::COOKIE_PATH);
    }

    public function slo(Request $request, SamlSingleLogout $logout, string $connection): Response
    {
        $model = $this->resolveSaml($connection);

        if ($request->query->has('SAMLRequest')) {
            return $this->answerIdpLogout($request, $logout, $model);
        }

        // The IdP's answer to the logout we started (the local session already ended).
        $pending = $request->session()->pull(self::LOGOUT_KEY);
        $requestId = is_array($pending) && is_string($pending['request_id'] ?? null) ? $pending['request_id'] : '';
        $returnUrl = is_array($pending) && is_string($pending['return_url'] ?? null) ? $pending['return_url'] : null;
        $confirmed = $requestId !== '' && $logout->confirms($model, $request, $requestId);

        return $this->backToApp($returnUrl, ['logout' => $confirmed ? 'ok' : 'partial'], loggedOut: true);
    }

    public function metadata(SamlIdentityProvider $saml, string $connection): Response
    {
        return response($saml->serviceProviderMetadata($this->resolveSaml($connection)), Response::HTTP_OK, [
            'Content-Type' => 'application/samlmetadata+xml',
        ]);
    }

    /**
     * IdP-initiated logout: a valid, signed LogoutRequest for the NameID this
     * browser's session logged in with ends that session; either way the IdP
     * gets its LogoutResponse.
     */
    private function answerIdpLogout(Request $request, SamlSingleLogout $logout, SsoConnection $connection): Response
    {
        try {
            $answer = $logout->answer($connection, $request);
        } catch (SsoAuthenticationException) {
            return response()->view('sso.result', ['ok' => false, 'logout' => true, 'invalid' => true], Response::HTTP_BAD_REQUEST);
        }

        $session = $request->session()->get(self::SESSION_KEY);

        if (is_array($session)
            && ($session['connection'] ?? null) === $connection->ulid
            && is_string($session['name_id'] ?? null)
            && hash_equals($session['name_id'], $answer['name_id'])) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return new RedirectResponse($answer['response_url']);
    }

    /**
     * Back to the first-party app the flow started from, or a plain page when
     * there is no trustworthy place to redirect to.
     *
     * @param  array<string, string>  $query
     */
    private function backToApp(?string $returnUrl, array $query, bool $loggedOut = false): RedirectResponse|HttpResponse
    {
        if ($returnUrl === null || ! FirstPartyUrl::matches($returnUrl)) {
            $ok = ! isset($query['error']);

            return response()->view('sso.result', ['ok' => $ok, 'logout' => $loggedOut], $ok ? Response::HTTP_OK : Response::HTTP_UNAUTHORIZED);
        }

        $separator = str_contains($returnUrl, '?') ? '&' : '?';

        return new RedirectResponse($returnUrl.$separator.http_build_query($query));
    }

    private function resolveSaml(string $connection): SsoConnection
    {
        $model = SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('ulid', $connection)
            ->where('provider', SsoProvider::Saml)
            ->where('is_active', true)
            ->first();

        if ($model === null) {
            abort(Response::HTTP_NOT_FOUND, 'SSO connection not found.');
        }

        return $model;
    }
}
